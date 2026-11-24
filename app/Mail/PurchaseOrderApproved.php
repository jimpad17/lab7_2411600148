<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Purchase Order {$this->order->reference_number} approved",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.purchase-order-approved',
            with: [
                'order' => $this->order->loadMissing(['supplier', 'approvedBy', 'items.product']),
                'url' => route('purchase-orders.show', $this->order),
            ],
        );
    }
}
