<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePublicAppointmentRequest;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;

/**
 * Portal público "Solicitá tu cita". NO crea una cita: genera un Lead con
 * `source=appointment` para que recepción revise disponibilidad y agende.
 * Sin auth, CSRF-exento (api/public/*), throttle `appointment-request`.
 */
class PublicAppointmentController extends Controller
{
    use ResolvesCompany;

    public function store(StorePublicAppointmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $ok = response()->json([
            'message' => 'Recibimos tu solicitud. La clínica confirmará disponibilidad y te contactará.',
        ]);

        // Honeypot: descarte silencioso.
        if (! empty($data['company_website'])) {
            return $ok;
        }

        $message = collect([
            $data['message'] ?? null,
            ! empty($data['pet_name']) ? 'Mascota: '.$data['pet_name'] : null,
            ! empty($data['reason']) ? 'Motivo: '.$data['reason'] : null,
            ! empty($data['preferred_date']) ? 'Fecha preferida: '.$data['preferred_date'] : null,
        ])->filter()->implode(' — ');

        $companyId = $this->companyId($request);

        // Una solicitud = una fila (la clínica agenda cada una). Solo se
        // colapsa el doble-click: mismo correo y misma solicitud en 5 minutos.
        $duplicate = Lead::query()
            ->where('company_id', $companyId)
            ->where('email', $data['email'])
            ->where('source', 'appointment')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if (! $duplicate) {
            Lead::create([
                'company_id' => $companyId,
                'vertical' => $data['vertical'] ?? 'ips',
                'email' => $data['email'],
                'source' => 'appointment',
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'message' => $message ?: null,
                'status' => 'new',
                'ip_address' => $request->ip(),
            ]);
        }

        return $ok;
    }
}
