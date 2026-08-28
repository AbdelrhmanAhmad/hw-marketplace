<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'partner_type', 'revenue_share_percentage'])]
class Partner extends Model
{
    public function marketplaceItems(): HasMany
    {
        return $this->hasMany(MarketplaceItem::class);
    }
}
