<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Purchase Order {$this->order->reference_number} requires approval",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.purchase-order-created',
            with: [
                'order' => $this->order->loadMissing(['supplier', 'createdBy', 'items.product']),
                'url' => route('purchase-orders.show', $this->order),
            ],
        );
    }

    /** Attach a printable copy of the order for the approver's records. */
    public function attachments(): array
    {
        $order = $this->order->loadMissing(['supplier', 'items.product']);

        $pdf = Pdf::loadView('emails.purchase-order-pdf', ['order' => $order])
            ->setPaper('a4')
            ->output();

        return [
            Attachment::fromData(fn () => $pdf, "purchase-order-{$this->order->reference_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
