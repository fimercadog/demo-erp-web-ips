<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGoogleCalendarAppointmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(public Appointment $appointment, public string $action = 'sync') {}

    /**
     * Execute the job directly in Laravel Queue worker.
     */
    public function handle(GoogleCalendarService $googleCalendarService): void
    {
        try {
            if ($this->action === 'delete') {
                $googleCalendarService->deleteGoogleEvent($this->appointment);
            } else {
                $googleCalendarService->syncAppointmentToGoogle($this->appointment);
            }
        } catch (\Throwable $e) {
            Log::error('SyncGoogleCalendarAppointmentJob failed', [
                'appointment_id' => $this->appointment->id,
                'action' => $this->action,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->appointment->update([
                    'sync_status' => 'error',
                    'sync_error' => 'Job retries exhausted: ' . substr($e->getMessage(), 0, 250),
                ]);
            }

            throw $e;
        }
    }
}
