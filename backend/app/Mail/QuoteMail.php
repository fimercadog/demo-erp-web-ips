<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class QuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote)
    {
    }

    public function envelope(): Envelope
    {
        $companyName = $this->quote->company->name ?? 'FidelOS';
        return new Envelope(
            subject: "Cotización Comercial #{$this->quote->id} - {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>Hola, {$this->getRecipientName()}</h2>
                    <p>Adjunto a este correo encontrarás la cotización comercial <strong>#{$this->quote->id}</strong> por un valor total de <strong>$" . number_format($this->quote->total, 0, ',', '.') . " COP</strong>.</p>
                    <p>Vertical: <strong>" . strtoupper($this->quote->vertical ?? 'General') . "</strong></p>
                    <p>Si tienes alguna inquietud sobre tu cotización o deseas continuar con el servicio, responder a este correo o contáctanos directamente.</p>
                    <br>
                    <p>Atentamente,<br><strong>" . ($this->quote->company->name ?? 'FidelOS ERP') . "</strong></p>
                </div>
            ",
        );
    }

    public function attachments(): array
    {
        if ($this->quote->pdf_path && Storage::disk('public')->exists($this->quote->pdf_path)) {
            return [
                Attachment::fromPath(Storage::disk('public')->path($this->quote->pdf_path))
                    ->as("Cotizacion_{$this->quote->id}.pdf")
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }

    private function getRecipientName(): string
    {
        return $this->quote->client->name ?? $this->quote->lead->name ?? 'Cliente';
    }
}
