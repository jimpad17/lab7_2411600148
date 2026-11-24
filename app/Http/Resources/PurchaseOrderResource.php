<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier?->company_name),
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_badge' => $this->status_badge_class,
            'order_date' => $this->order_date?->format('Y-m-d'),
            'expected_delivery_date' => $this->expected_delivery_date?->format('Y-m-d'),
            'total' => (float) $this->total,
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'is_overdue' => $this->is_overdue,
            'url' => route('purchase-orders.show', $this),
        ];
    }
}
