<?php

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    public function __construct(
        public $products,
        public int $purchaseOrdersGenerated = 0
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Automated Reorder Alert — '.now()->format('M d, Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.low-stock-alert',
            with: [
                'products' => $this->products,
                'purchaseOrdersGenerated' => $this->purchaseOrdersGenerated,
                'url' => route('dashboard'),
            ],
        );
    }
}
