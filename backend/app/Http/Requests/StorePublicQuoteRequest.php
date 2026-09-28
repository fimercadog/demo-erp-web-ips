<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Validation\Rule;

/**
 * Solicitud de cotizacion desde el catalogo publico. Sin usuario autenticado:
 * la empresa es la primera del tenant (igual que StoreLeadRequest). Cada linea
 * debe apuntar a un producto realmente publico y activo.
 */
class StorePublicQuoteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        // Mismo criterio que ResolvesCompany::companyId en el controlador, para
        // que la validacion y la escritura miren siempre la misma empresa.
        $companyId = app(\App\Services\PublicTenantResolverService::class)->resolveCompanyId($this);

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'vertical' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:2000'],
            // Ley 1581: sin consentimiento no se guardan datos de contacto.
            'consent' => ['accepted'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => [
                'nullable', 'integer', 'distinct',
                Rule::exists('products', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_public', true)
                    ->where('status', 'active'),
            ],
            'items.*.service_id' => [
                'nullable', 'integer',
                Rule::exists('services', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_public', true)
                    ->where('status', 'active'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'consent.accepted' => 'Debes autorizar el tratamiento de datos para enviar la solicitud.',
            'items.required' => 'Agrega al menos un producto a la cotizacion.',
            'items.min' => 'Agrega al menos un producto a la cotizacion.',
        ];
    }
}
