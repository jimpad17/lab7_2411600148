<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Notifications\Notification;

/**
 * In-app bell entry for Lab 7 purchase-order events. Deliverable emails are
 * handled by dedicated Mailable classes; this notification only writes to the
 * database channel so the alert appears instantly without a queue worker.
 */
class PurchaseOrderNotification extends Notification
{
    public function __construct(
        public PurchaseOrder $order,
        public string $event,
        public string $title,
        public string $message
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'event' => $this->event,
            'purchase_order_id' => $this->order->id,
            'reference_number' => $this->order->reference_number,
            'status' => $this->order->status,
            'product_name' => $this->order->supplier?->company_name ?? 'Supplier',
            'suggested_action' => 'Open the purchase order to review details.',
            'url' => route('purchase-orders.show', $this->order),
            'icon' => 'bi-cart-plus',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
