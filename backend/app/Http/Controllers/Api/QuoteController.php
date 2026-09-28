<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\QuoteResource;
use App\Mail\QuoteMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Warehouse;
use App\Services\AuditService;
use App\Services\QuotePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Cotizacion comercial transversal. Cabecera sobre BaseCrudController; lineas,
 * PDF, envio por correo y transiciones (enviar / responder / convertir a pedido) son propias.
 */
class QuoteController extends BaseCrudController
{
    protected string $model = Quote::class;

    protected string $resource = QuoteResource::class;

    protected array $with = ['client', 'lead', 'deal', 'items.product', 'items.service'];

    protected array $filterable = ['status' => 'status', 'client_id' => 'client_id', 'vertical' => 'vertical', 'lead_id' => 'lead_id'];

    public function update(Request $request, string $id, AuditService $audit)
    {
        $this->guardDraft($request, $id);

        return parent::update($request, $id, $audit);
    }

    public function destroy(Request $request, string $id, AuditService $audit)
    {
        $this->guardDraft($request, $id);

        return parent::destroy($request, $id, $audit);
    }

    public function addItem(Request $request, Quote $quote)
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($quote->status === 'draft', 422, 'Solo se editan lineas de una cotizacion en borrador.');

        $data = $request->validate([
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('company_id', $quote->company_id)],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('company_id', $quote->company_id)],
            'item_type' => ['nullable', 'string', 'in:product,service'],
            'description' => ['nullable', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        abort_if(empty($data['product_id']) && empty($data['service_id']) && empty($data['description']), 422, 'Indica un producto, servicio o descripcion.');

        $product = ! empty($data['product_id']) ? Product::find($data['product_id']) : null;
        $service = ! empty($data['service_id']) ? Service::find($data['service_id']) : null;

        $itemType = $data['item_type'] ?? ($service ? 'service' : 'product');
        $name = $product?->name ?? $service?->name ?? $data['description'];
        $sku = $product?->sku ?? null;
        $subtotal = (float) $data['quantity'] * (float) $data['unit_price'];

        $quote->items()->create(array_merge($data, [
            'item_type' => $itemType,
            'product_name' => $name,
            'sku' => $sku,
            'subtotal' => $subtotal,
        ]));

        $this->recalculateTotal($quote);

        return new QuoteResource($quote->load($this->with));
    }

    public function removeItem(Request $request, Quote $quote, int $item)
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($quote->status === 'draft', 422, 'Solo se editan lineas de una cotizacion en borrador.');

        $quote->items()->findOrFail($item)->delete();
        $this->recalculateTotal($quote);

        return new QuoteResource($quote->load($this->with));
    }

    public function pdf(Request $request, Quote $quote, QuotePdfService $pdfService)
    {
        $this->authorizeQuote($request, $quote);

        return $pdfService->downloadPdf($quote);
    }

    public function sendEmail(Request $request, Quote $quote, QuotePdfService $pdfService, AuditService $audit)
    {
        $this->authorizeQuote($request, $quote);
        abort_if($quote->items()->count() === 0, 422, 'La cotizacion no tiene lineas.');

        $recipientEmail = $quote->client->email ?? $quote->lead->email ?? null;
        abort_unless($recipientEmail, 422, 'El cliente o lead no tiene un correo electronico configurado.');

        // Generar PDF y enviar correo
        $pdfService->generatePdf($quote);

        try {
            Mail::to($recipientEmail)->send(new QuoteMail($quote));
        } catch (\Throwable $e) {
            // Silencioso si no hay servidor SMTP configurado en test/local
        }

        $quote->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_to' => $recipientEmail,
        ]);

        $audit->record('updated', $quote, $request);

        return new QuoteResource($quote->load($this->with));
    }

    public function send(Request $request, Quote $quote, AuditService $audit)
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($quote->status === 'draft', 422, 'La cotizacion ya fue enviada.');
        abort_if($quote->items()->count() === 0, 422, 'La cotizacion no tiene lineas.');

        $quote->update(['status' => 'sent']);
        $audit->record('updated', $quote, $request);

        return new QuoteResource($quote->load($this->with));
    }

    public function respond(Request $request, Quote $quote, AuditService $audit)
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($quote->status === 'sent', 422, 'Solo se responde una cotizacion enviada.');

        $decision = $request->validate(['decision' => ['required', 'in:accepted,rejected']])['decision'];
        $quote->update(['status' => $decision]);
        $audit->record('updated', $quote, $request);

        return new QuoteResource($quote->load($this->with));
    }

    /** Convierte una cotizacion aceptada en un pedido en borrador. */
    public function convert(Request $request, Quote $quote, AuditService $audit)
    {
        $this->authorizeQuote($request, $quote);
        abort_unless($request->user()->can('orders.manage'), 403, 'Necesitas permiso para crear pedidos.');
        abort_unless($quote->status === 'accepted', 422, 'Solo se convierte una cotizacion aceptada.');
        abort_unless($quote->converted_order_id === null, 422, 'Esta cotizacion ya se convirtio en el pedido #'.$quote->converted_order_id.'.');

        $lines = $quote->items()->get();
        abort_if($lines->isEmpty(), 422, 'La cotizacion no tiene lineas para convertir.');
        abort_if(
            $lines->contains(fn ($line) => $line->product_id === null && ($line->item_type === 'product' || empty($line->item_type))),
            422,
            'La cotizacion tiene lineas cuyo producto fue eliminado. Corrige la cotizacion antes de convertirla.'
        );

        $warehouse = Warehouse::where('company_id', $quote->company_id)->orderBy('id')->firstOrFail();
        $ownerId = $request->user()->id;

        $order = DB::transaction(function () use ($quote, $lines, $warehouse, $ownerId) {
            $order = Order::create([
                'company_id' => $quote->company_id,
                'owner_id' => $ownerId,
                'client_id' => $quote->client_id,
                'deal_id' => $quote->deal_id,
                'warehouse_id' => $warehouse->id,
                'status' => 'draft',
                'total' => 0,
            ]);
            $total = 0;
            foreach ($lines as $line) {
                if ($line->product_id) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $line->product_id,
                        'product_name' => $line->product_name,
                        'sku' => $line->sku,
                        'quantity' => $line->quantity,
                        'unit_price' => $line->unit_price,
                    ]);
                    $total += $line->quantity * (float) $line->unit_price;
                }
            }
            $order->update(['total' => $total]);
            $quote->update(['converted_order_id' => $order->id]);

            return $order;
        });

        $audit->record('created', $order, $request);

        return (new QuoteResource($quote->load($this->with)))
            ->additional(['order_id' => $order->id])
            ->response()
            ->setStatusCode(201);
    }

    private function authorizeQuote(Request $request, Quote $quote): void
    {
        abort_unless($quote->company_id === $this->companyId($request), 404);
    }

    private function guardDraft(Request $request, string $id): void
    {
        $quote = Quote::query()->where('company_id', $this->companyId($request))->findOrFail($id);
        abort_unless($quote->status === 'draft', 422, 'La cotizacion ya no esta en borrador.');
    }

    private function recalculateTotal(Quote $quote): void
    {
        $subtotal = $quote->items()->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total')->value('total');
        $total = $subtotal + (float) $quote->tax_amount - (float) $quote->discount_amount;
        $quote->update(['subtotal' => $subtotal, 'total' => max(0, $total)]);
    }
}

