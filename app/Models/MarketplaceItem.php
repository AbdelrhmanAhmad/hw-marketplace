<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'key', 'type', 'partner_id', 'category_id', 'name', 'tagline', 'description',
    'icon', 'status', 'billing_model', 'pricing_model', 'compatibility', 'version',
])]
class MarketplaceItem extends Model
{
    protected function casts(): array
    {
        return [
            'compatibility' => 'array',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'category_id');
    }

    public function applicationDetail(): HasOne
    {
        return $this->hasOne(ApplicationDetail::class);
    }
}
