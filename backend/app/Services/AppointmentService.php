<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppointmentService
{
    public function __construct(
        private readonly SchedulingService $scheduling,
        private readonly GoogleCalendarService $googleCalendarService
    ) {}

    public function checkAvailability(
        int $companyId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $practitionerId = null,
        ?string $resource = null,
        ?int $excludeAppointmentId = null
    ): bool {
        if ($practitionerId) {
            $practitionerBusy = Appointment::query()
                ->where('company_id', $companyId)
                ->where('practitioner_id', $practitionerId)
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->when($excludeAppointmentId, fn ($q) => $q->where('id', '!=', $excludeAppointmentId))
                ->exists();

            if ($practitionerBusy) {
                return false;
            }
        }

        if (! empty($resource)) {
            $resourceBusy = Appointment::query()
                ->where('company_id', $companyId)
                ->where('resource', $resource)
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->when($excludeAppointmentId, fn ($q) => $q->where('id', '!=', $excludeAppointmentId))
                ->exists();

            if ($resourceBusy) {
                return false;
            }
        }

        return true;
    }

    public function createAppointment(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            $startsAt = Carbon::parse($data['starts_at']);
            
            if (empty($data['ends_at'])) {
                $duration = $data['duration_minutes'] ?? 30;
                if (! empty($data['service_id'])) {
                    $service = Service::find($data['service_id']);
                    if ($service && $service->estimated_duration_minutes) {
                        $duration = $service->estimated_duration_minutes;
                    }
                }
                $endsAt = $startsAt->copy()->addMinutes($duration);
            } else {
                $endsAt = Carbon::parse($data['ends_at']);
            }

            $companyId = $data['company_id'];
            $practitionerId = $data['practitioner_id'] ?? null;
            $resource = $data['resource'] ?? null;

            abort_unless(
                $this->checkAvailability($companyId, $startsAt, $endsAt, $practitionerId, $resource),
                422,
                'El profesional o recurso especificado ya no está disponible en ese horario.'
            );

            if (empty($data['price']) && ! empty($data['service_id'])) {
                $service = Service::find($data['service_id']);
                if ($service && $service->price) {
                    $data['price'] = $service->price;
                }
            }

            $data['starts_at'] = $startsAt->toDateTimeString();
            $data['ends_at'] = $endsAt->toDateTimeString();
            $data['status'] = $data['status'] ?? 'scheduled';

            $appointment = Appointment::create($data);

            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => auth()->id(),
                'action' => 'APPOINTMENT_CREATED',
                'module' => 'appointments',
                'entity' => 'Appointment',
                'entity_id' => $appointment->id,
                'new_values' => $appointment->toArray(),
            ]);

            try {
                $this->googleCalendarService->syncAppointmentToGoogle($appointment);
            } catch (\Throwable $e) {
                // Non-blocking sync error
            }

            return $appointment;
        });
    }

    public function rescheduleAppointment(Appointment $appointment, Carbon $newStartsAt, ?Carbon $newEndsAt = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $newStartsAt, $newEndsAt) {
            $duration = $appointment->duration_minutes ?? 30;
            $endsAt = $newEndsAt ? Carbon::parse($newEndsAt) : $newStartsAt->copy()->addMinutes($duration);

            abort_unless(
                $this->checkAvailability(
                    $appointment->company_id,
                    $newStartsAt,
                    $endsAt,
                    $appointment->practitioner_id,
                    $appointment->resource,
                    $appointment->id
                ),
                422,
                'Ese horario entra en conflicto con otra cita agendada.'
            );

            $oldValues = $appointment->toArray();

            $appointment->update([
                'starts_at' => $newStartsAt->toDateTimeString(),
                'ends_at' => $endsAt->toDateTimeString(),
                'duration_minutes' => max(1, (int) $newStartsAt->diffInMinutes($endsAt)),
            ]);

            AuditLog::create([
                'company_id' => $appointment->company_id,
                'user_id' => auth()->id(),
                'action' => 'APPOINTMENT_RESCHEDULED',
                'module' => 'appointments',
                'entity' => 'Appointment',
                'entity_id' => $appointment->id,
                'old_values' => $oldValues,
                'new_values' => $appointment->toArray(),
            ]);

            try {
                $this->googleCalendarService->syncAppointmentToGoogle($appointment);
            } catch (\Throwable $e) {
                // Non-blocking sync error
            }

            return $appointment;
        });
    }

    public function markAttended(Appointment $appointment, bool $autoBill = false): Appointment
    {
        return DB::transaction(function () use ($appointment, $autoBill) {
            $oldStatus = $appointment->status;
            $appointment->update(['status' => 'attended']);

            if ($autoBill && (float) $appointment->price > 0 && ! $appointment->invoice_id) {
                $clientId = $appointment->client_id;
                if (! $clientId && $appointment->patient_id) {
                    $clientId = $appointment->patient?->client_id;
                }

                if ($clientId) {
                    $invoiceNumber = 'INV-APT-' . date('Ymd') . '-' . strtoupper(Str::random(4));
                    $issueDate = now()->toDateString();
                    $dueDate = now()->addDays(5)->toDateString();

                    $invoice = Invoice::create([
                        'company_id' => $appointment->company_id,
                        'branch_id' => $appointment->branch_id,
                        'client_id' => $clientId,
                        'number' => $invoiceNumber,
                        'issue_date' => $issueDate,
                        'due_date' => $dueDate,
                        'status' => 'pending',
                        'subtotal' => $appointment->price,
                        'discount' => 0,
                        'tax' => 0,
                        'total' => $appointment->price,
                        'notes' => 'Factura generada por cita #' . $appointment->id,
                    ]);

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'product_name' => 'Servicio de cita: ' . ($appointment->service?->name ?? 'Cita General'),
                        'quantity' => 1,
                        'unit_price' => $appointment->price,
                        'discount' => 0,
                        'tax' => 0,
                        'line_total' => $appointment->price,
                    ]);

                    $cxc = AccountReceivable::create([
                        'company_id' => $appointment->company_id,
                        'client_id' => $clientId,
                        'invoice_id' => $invoice->id,
                        'original_amount' => $appointment->price,
                        'paid_amount' => 0,
                        'balance' => $appointment->price,
                        'due_date' => $dueDate,
                        'status' => 'pending',
                    ]);

                    $appointment->update([
                        'invoice_id' => $invoice->id,
                        'account_receivable_id' => $cxc->id,
                        'payment_status' => 'unpaid',
                    ]);
                }
            }

            AuditLog::create([
                'company_id' => $appointment->company_id,
                'user_id' => auth()->id(),
                'action' => 'APPOINTMENT_ATTENDED',
                'module' => 'appointments',
                'entity' => 'Appointment',
                'entity_id' => $appointment->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => $appointment->toArray(),
            ]);

            return $appointment;
        });
    }

    public function cancelAppointment(Appointment $appointment, ?string $reason = null): Appointment
    {
        $oldStatus = $appointment->status;

        $appointment->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        AuditLog::create([
            'company_id' => $appointment->company_id,
            'user_id' => auth()->id(),
            'action' => 'APPOINTMENT_CANCELLED',
            'module' => 'appointments',
            'entity' => 'Appointment',
            'entity_id' => $appointment->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => $appointment->toArray(),
        ]);

        try {
            $this->googleCalendarService->deleteGoogleEvent($appointment);
        } catch (\Throwable $e) {
            // Non-blocking sync error
        }

        return $appointment;
    }
}
