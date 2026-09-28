<?php

namespace App\Http\Requests;

class StorePublicAppointmentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vertical' => ['nullable', 'string', 'max:50'],
            'pet_name' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:255'],
            'preferred_date' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:2000'],
            // Ley 1581: consentimiento obligatorio.
            'consent' => ['accepted'],
            // Honeypot: campo oculto. Si viene relleno, el controller descarta
            // la solicitud en silencio (no 422, para no darle señal al bot).
            'company_website' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'consent.accepted' => 'Debes autorizar el tratamiento de datos para enviar la solicitud.',
        ];
    }
}
