<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['marketplace_item_id', 'name', 'seat_limit', 'price', 'billing_cycle'])]
class SubscriptionPlan extends Model
{
    public function marketplaceItem(): BelongsTo
    {
        return $this->belongsTo(MarketplaceItem::class);
    }
}
