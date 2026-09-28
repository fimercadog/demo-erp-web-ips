<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePublicQuoteRequest;
use App\Http\Resources\PublicProductResource;
use App\Http\Resources\PublicServiceResource;
use App\Models\Category;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo público transversal del sitio de marketing. Sin auth (throttle en las rutas).
 * Expone productos y servicios con `is_public = true` y `status = active` filtrados por company_id y vertical.
 */
class PublicCatalogController extends Controller
{
    use ResolvesCompany;

    public function products(Request $request)
    {
        $products = $this->publicProducts($request)
            ->with(['category', 'brand', 'unit'])
            ->when($request->filled('vertical'), fn ($q) => $q->where('vertical', $request->string('vertical')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.addcslashes((string) $request->string('q'), '%_\\').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('sku', 'like', $term));
            })
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(max(1, min($request->integer('per_page', 12), 48)));

        return PublicProductResource::collection($products);
    }

    public function product(Request $request, int $id)
    {
        $product = $this->publicProducts($request)
            ->with(['category', 'brand', 'unit'])
            ->findOrFail($id);

        return new PublicProductResource($product);
    }

    public function services(Request $request)
    {
        $services = $this->publicServices($request)
            ->with(['category'])
            ->when($request->filled('vertical'), fn ($q) => $q->where('vertical', $request->string('vertical')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.addcslashes((string) $request->string('q'), '%_\\').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return PublicServiceResource::collection($services);
    }

    public function service(Request $request, int $id)
    {
        $service = $this->publicServices($request)
            ->with(['category'])
            ->findOrFail($id);

        return new PublicServiceResource($service);
    }

    public function categories(Request $request)
    {
        return Category::query()
            ->where('company_id', $this->companyId($request))
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereHas('products', fn ($p) => $p->where('is_public', true)->where('status', 'active'))
                  ->orWhereHas('services', fn ($s) => $s->where('is_public', true)->where('status', 'active'));
            })
            ->when($request->filled('vertical'), fn ($q) => $q->where('vertical', $request->string('vertical')))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'vertical']);
    }

    public function storeQuoteRequest(StorePublicQuoteRequest $request, AuditService $audit, \App\Services\QuotePdfService $pdfService)
    {
        $companyId = $this->companyId($request);
        $data = $request->validated();
        $vertical = $data['vertical'] ?? 'ips';

        $productIds = collect($data['items'])->pluck('product_id')->filter()->all();
        $serviceIds = collect($data['items'])->pluck('service_id')->filter()->all();

        $productsById = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $servicesById = Service::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $serviceIds)
            ->get()
            ->keyBy('id');

        foreach ($data['items'] as $item) {
            if (! empty($item['product_id'])) {
                abort_unless($productsById->has($item['product_id']), 422, 'Un producto de la solicitud ya no está disponible.');
            }
            if (! empty($item['service_id'])) {
                abort_unless($servicesById->has($item['service_id']), 422, 'Un servicio de la solicitud ya no está disponible.');
            }
        }

        $lead = Lead::firstOrCreate(
            ['company_id' => $companyId, 'email' => $data['email']],
            [
                'vertical' => $vertical,
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'interested_product_id' => $productIds[0] ?? null,
                'interested_service_id' => $serviceIds[0] ?? null,
                'message' => $data['message'] ?? null,
                'source' => 'catalog',
                'status' => 'new',
                'ip_address' => $request->ip(),
            ],
        );

        $client = Client::firstOrCreate(
            ['company_id' => $companyId, 'email' => $data['email']],
            [
                'name' => $data['name'],
                'company_name' => $data['company_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'status' => 'inactive',
            ],
        );

        $quote = DB::transaction(function () use ($data, $companyId, $client, $lead, $vertical, $productsById, $servicesById) {
            $noteLines = array_filter([
                $data['message'] ?? null,
                'Solicitud desde el catálogo público.',
                'Contacto: '.$data['name'].($data['phone'] ?? null ? ' · Tel: '.$data['phone'] : ''),
                ($data['company_name'] ?? null) ? 'Empresa: '.$data['company_name'] : null,
            ]);

            $quote = Quote::create([
                'company_id' => $companyId,
                'client_id' => $client->id,
                'lead_id' => $lead->id,
                'vertical' => $vertical,
                'title' => 'Solicitud web — '.$data['name'],
                'status' => 'draft',
                'source' => 'catalog',
                'valid_until' => now()->addDays(30),
                'notes' => implode("\n", $noteLines),
                'subtotal' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $product = ! empty($item['product_id']) ? $productsById->get($item['product_id']) : null;
                $service = ! empty($item['service_id']) ? $servicesById->get($item['service_id']) : null;

                $unitPrice = $product ? (float) $product->unit_price : ($service ? (float) $service->price : 0);
                $name = $product?->name ?? $service?->name ?? 'Ítem general';
                $sku = $product?->sku ?? null;
                $itemType = $service ? 'service' : 'product';
                $lineSubtotal = $item['quantity'] * $unitPrice;

                $quote->items()->create([
                    'product_id' => $product?->id,
                    'service_id' => $service?->id,
                    'item_type' => $itemType,
                    'product_name' => $name,
                    'sku' => $sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                ]);
                $subtotal += $lineSubtotal;
            }
            $quote->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            return $quote;
        });

        // Generar PDF y enviar correo
        try {
            $pdfService->generatePdf($quote);
            \Illuminate\Support\Facades\Mail::to($data['email'])->send(new \App\Mail\QuoteMail($quote));
            $quote->update([
                'sent_at' => now(),
                'sent_to' => $data['email'],
            ]);
        } catch (\Throwable $e) {
            // Silencioso en caso de no SMTP en local/test
        }

        $audit->record('created', $quote, $request);

        return response()->json([
            'message' => 'Recibimos tu solicitud de cotización. Te enviamos la propuesta PDF a tu correo.',
            'quote_id' => $quote->id,
            'lead_id' => $lead->id,
        ], 201);
    }

    /** Query base de productos públicos: tenant + is_public + activo. */
    private function publicProducts(Request $request): Builder
    {
        return Product::query()
            ->where('company_id', $this->companyId($request))
            ->where('is_public', true)
            ->where('status', 'active');
    }

    /** Query base de servicios públicos: tenant + is_public + activo. */
    private function publicServices(Request $request): Builder
    {
        return Service::query()
            ->where('company_id', $this->companyId($request))
            ->where('is_public', true)
            ->where('status', 'active');
    }
}
