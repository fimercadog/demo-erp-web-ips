<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\GoogleCalendarIntegration;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Motor de disponibilidad de citas (S13) y Agendamiento Transversal FidelOS Core.
 * Compartido por el booking público (IPS, Estética, Vet, Odontología, Sueroterapia)
 * y el ERP administrativo para no duplicar la lógica de choque de horarios
 * y sincronización bidireccional con Google Calendar.
 */
class SchedulingService
{
    protected GoogleCalendarService $googleCalendarService;

    public function __construct(GoogleCalendarService $googleCalendarService)
    {
        $this->googleCalendarService = $googleCalendarService;
    }

    /**
     * Aborta 422 si `[startsAt, endsAt)` no cae dentro de un día hábil y del
     * horario de atención, o no cumple la anticipación mínima.
     */
    public function assertWithinBusinessWindow(Carbon $startsAt, Carbon $endsAt): void
    {
        $config = config('scheduling', [
            'min_notice_minutes' => 60,
            'business_days' => [1, 2, 3, 4, 5, 6],
            'business_hours' => ['start' => '08:00', 'end' => '18:00'],
            'slot_step_minutes' => 30,
            'default_duration_minutes' => 30,
            'max_days_ahead' => 60,
        ]);

        abort_if(
            $startsAt->lt(Carbon::now()->addMinutes($config['min_notice_minutes'] ?? 60)),
            422,
            'Ese horario ya no tiene la anticipación mínima requerida. Elegí otro.',
        );
        abort_unless(in_array($startsAt->isoWeekday(), $config['business_days'] ?? [1, 2, 3, 4, 5, 6], true), 422, 'La clínica no atiende ese día.');

        $startHour = $config['business_hours']['start'] ?? '08:00';
        $endHour = $config['business_hours']['end'] ?? '18:00';

        $dayStart = $startsAt->copy()->setTimeFromTimeString($startHour);
        $dayEnd = $startsAt->copy()->setTimeFromTimeString($endHour);
        abort_unless($startsAt->gte($dayStart) && $endsAt->lte($dayEnd), 422, 'Ese horario está fuera del horario de atención.');
    }

    /**
     * Devuelve los horarios libres "H:i" considerando FidelOS DB + Google Calendar FreeBusy.
     * @return list<string>
     */
    public function freeSlots(int $companyId, Service $service, Carbon $date, ?int $excludeAppointmentId = null, ?int $practitionerId = null): array
    {
        $config = config('scheduling', [
            'min_notice_minutes' => 60,
            'business_days' => [1, 2, 3, 4, 5, 6],
            'business_hours' => ['start' => '08:00', 'end' => '18:00'],
            'slot_step_minutes' => 30,
            'default_duration_minutes' => 30,
            'max_days_ahead' => 60,
        ]);

        $today = Carbon::today();
        $maxDays = $config['max_days_ahead'] ?? 60;

        if ($date->lt($today) || $date->gt($today->copy()->addDays($maxDays))) {
            return [];
        }
        if (! in_array($date->isoWeekday(), $config['business_days'] ?? [1, 2, 3, 4, 5, 6], true)) {
            return [];
        }

        $practitioners = $practitionerId 
            ? User::where('company_id', $companyId)->where('id', $practitionerId)->get(['id', 'name'])
            : $this->practitioners($companyId);

        if ($practitioners->isEmpty()) {
            return [];
        }

        $duration = $service->estimated_duration_minutes ?? $service->duration_minutes ?? ($config['default_duration_minutes'] ?? 30);
        $startHour = $config['business_hours']['start'] ?? '08:00';
        $endHour = $config['business_hours']['end'] ?? '18:00';
        $step = $config['slot_step_minutes'] ?? 30;

        $dayStart = $date->copy()->setTimeFromTimeString($startHour);
        $dayEnd = $date->copy()->setTimeFromTimeString($endHour);
        $earliestAllowed = Carbon::now()->addMinutes($config['min_notice_minutes'] ?? 60);

        // Fetch internal FidelOS appointments
        $busyFidelOS = Appointment::query()
            ->where('company_id', $companyId)
            ->whereIn('practitioner_id', $practitioners->pluck('id'))
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->whereDate('starts_at', $date->toDateString())
            ->when($excludeAppointmentId, fn ($q) => $q->where('id', '!=', $excludeAppointmentId))
            ->get(['id', 'practitioner_id', 'starts_at', 'ends_at']);

        // Fetch Google Calendar FreeBusy slots for practitioners with active integrations
        $googleBusyByPractitioner = [];
        $integrations = GoogleCalendarIntegration::where('company_id', $companyId)
            ->where('sync_enabled', true)
            ->get();

        foreach ($integrations as $integration) {
            $busySlots = $this->googleCalendarService->getFreeBusySlots(
                $integration,
                $date->toDateString(),
                $date->toDateString()
            );

            $pId = $integration->user_id ?: 0;
            if (! isset($googleBusyByPractitioner[$pId])) {
                $googleBusyByPractitioner[$pId] = [];
            }
            $googleBusyByPractitioner[$pId] = array_merge($googleBusyByPractitioner[$pId], $busySlots);
        }

        $slots = [];
        for ($slotStart = $dayStart->copy(); $slotStart->copy()->addMinutes($duration)->lte($dayEnd); $slotStart->addMinutes($step)) {
            if ($slotStart->lt($earliestAllowed)) {
                continue;
            }
            $slotEnd = $slotStart->copy()->addMinutes($duration);

            $hasFreePractitioner = $practitioners->contains(function ($practitioner) use ($slotStart, $slotEnd, $busyFidelOS, $googleBusyByPractitioner) {
                // Check FidelOS internal collision
                $fidelosCollision = $busyFidelOS->contains(
                    fn ($appt) => $appt->practitioner_id === $practitioner->id
                        && $appt->starts_at->lt($slotEnd) && $appt->ends_at->gt($slotStart)
                );

                if ($fidelosCollision) {
                    return false;
                }

                // Check Google Calendar collision (practitioner specific or company default)
                $googleSlots = array_merge(
                    $googleBusyByPractitioner[$practitioner->id] ?? [],
                    $googleBusyByPractitioner[0] ?? []
                );

                foreach ($googleSlots as $gSlot) {
                    if ($gSlot['start']->lt($slotEnd) && $gSlot['end']->gt($slotStart)) {
                        return false; // Collision with Google Calendar event
                    }
                }

                return true; // Free!
            });

            if ($hasFreePractitioner) {
                $slots[] = $slotStart->format('H:i');
            }
        }

        return $slots;
    }

    /**
     * @return Collection<int, User> Médicos, odontólogos, cosmetólogas, veterinarios y profesionales activos de la empresa
     */
    public function practitioners(int $companyId): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereHas('roles', fn ($q) => $q->whereIn('name', [
                    'Veterinario/a', 'Médico/a', 'Médico Tratante', 'Doctor/a',
                    'Especialista', 'Médico', 'Odontólogo/a', 'Odontologo',
                    'Cosmetóloga', 'Enfermero/a', 'Auxiliar'
                ]))
                ->orWhere('is_active', true);
            })
            ->get(['id', 'name', 'email']);
    }

    /**
     * Primer profesional libre bajo lock (anti-doble-booking).
     * Considera FidelOS DB + Google Calendar FreeBusy.
     */
    public function firstFreePractitioner(int $companyId, Carbon $startsAt, Carbon $endsAt, ?int $excludeAppointmentId = null, ?int $preferredPractitionerId = null): ?User
    {
        $practitioners = $preferredPractitionerId 
            ? User::where('company_id', $companyId)->where('id', $preferredPractitionerId)->get(['id', 'name', 'email'])
            : $this->practitioners($companyId);

        foreach ($practitioners as $practitioner) {
            $busy = Appointment::query()
                ->where('company_id', $companyId)
                ->where('practitioner_id', $practitioner->id)
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->when($excludeAppointmentId, fn ($q) => $q->where('id', '!=', $excludeAppointmentId))
                ->lockForUpdate()
                ->exists();

            if ($busy) {
                continue;
            }

            // Check Google Calendar FreeBusy
            $integration = GoogleCalendarIntegration::where('company_id', $companyId)
                ->where(fn ($q) => $q->where('user_id', $practitioner->id)->orWhereNull('user_id'))
                ->where('sync_enabled', true)
                ->first();

            if ($integration) {
                $gSlots = $this->googleCalendarService->getFreeBusySlots(
                    $integration,
                    $startsAt->toDateString(),
                    $endsAt->toDateString()
                );

                $googleBusy = false;
                foreach ($gSlots as $gSlot) {
                    if ($gSlot['start']->lt($endsAt) && $gSlot['end']->gt($startsAt)) {
                        $googleBusy = true;
                        break;
                    }
                }

                if ($googleBusy) {
                    continue;
                }
            }

            return $practitioner;
        }

        return null;
    }

    /**
     * Crear una cita pública o administrativa con validación de disponibilidad y sync a Google Calendar.
     */
    public function createAppointment(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            $companyId = $data['company_id'];
            $startsAt = Carbon::parse($data['starts_at']);
            $duration = (int) ($data['duration_minutes'] ?? 30);
            $endsAt = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : $startsAt->copy()->addMinutes($duration);

            $this->assertWithinBusinessWindow($startsAt, $endsAt);

            $practitioner = $this->firstFreePractitioner(
                $companyId,
                $startsAt,
                $endsAt,
                null,
                $data['practitioner_id'] ?? null
            );

            abort_unless($practitioner, 422, 'No hay profesional disponible en el horario seleccionado.');

            $appointment = Appointment::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'patient_id' => $data['patient_id'] ?? null,
                'client_id' => $data['client_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'practitioner_id' => $practitioner->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $duration,
                'resource' => $data['resource'] ?? null,
                'reason' => $data['reason'] ?? null,
                'status' => $data['status'] ?? 'scheduled',
                'price' => $data['price'] ?? null,
                'payment_status' => $data['payment_status'] ?? 'unpaid',
                'notes' => $data['notes'] ?? null,
                'is_domiciliary' => $data['is_domiciliary'] ?? false,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'neighborhood' => $data['neighborhood'] ?? null,
                'address_reference' => $data['address_reference'] ?? null,
                'dispatch_status' => $data['dispatch_status'] ?? 'pending',
            ]);

            // Sync to Google Calendar asynchronously / inline
            try {
                $this->googleCalendarService->syncAppointmentToGoogle($appointment);
            } catch (\Throwable $e) {
                // Non-blocking Google Calendar sync error
            }

            return $appointment;
        });
    }

    /**
     * Cancelar cita y remover de Google Calendar.
     */
    public function cancelAppointment(Appointment $appointment, ?string $reason = null): Appointment
    {
        $appointment->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        try {
            $this->googleCalendarService->deleteGoogleEvent($appointment);
        } catch (\Throwable $e) {
            // Non-blocking
        }

        return $appointment;
    }
}
