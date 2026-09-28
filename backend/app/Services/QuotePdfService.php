<?php

namespace App\Services;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class QuotePdfService
{
    /**
     * Genera el PDF de la cotización y lo guarda en el storage público.
     * Retorna la ruta relativa guardada.
     */
    public function generatePdf(Quote $quote): string
    {
        $quote->loadMissing(['company', 'client', 'lead', 'items.product', 'items.service']);
        $company = $quote->company;

        $pdf = Pdf::loadView('pdfs.quote', [
            'quote' => $quote,
            'company' => $company,
        ])->setPaper('a4', 'portrait');

        $filename = "quotes/{$quote->company_id}/quote_{$quote->id}.pdf";
        Storage::disk('public')->put($filename, $pdf->output());

        $quote->update(['pdf_path' => $filename]);

        return $filename;
    }

    /**
     * Retorna la descarga directa del PDF.
     */
    public function downloadPdf(Quote $quote)
    {
        if (! $quote->pdf_path || ! Storage::disk('public')->exists($quote->pdf_path)) {
            $this->generatePdf($quote);
        }

        return Storage::disk('public')->download($quote->pdf_path, "cotizacion_{$quote->id}.pdf");
    }
}
