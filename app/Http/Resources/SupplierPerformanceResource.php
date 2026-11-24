<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPerformanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'total_orders' => (int) ($this->total_orders ?? 0),
            'delivered_orders' => (int) ($this->delivered_orders ?? 0),
            'delivered_on_time' => (int) ($this->delivered_on_time ?? 0),
            'on_time_rate' => (float) ($this->on_time_rate ?? 0),
            'avg_delivery_days' => (float) ($this->avg_delivery_days ?? 0),
            'avg_quoted_days' => (float) ($this->avg_quoted_days ?? 0),
            'avg_variance_days' => (float) ($this->avg_variance_days ?? 0),
            'accuracy_rate' => (float) ($this->accuracy_rate ?? 0),
            'total_value' => (float) ($this->total_value ?? 0),
        ];
    }
}
