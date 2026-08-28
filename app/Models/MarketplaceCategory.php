<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
class MarketplaceCategory extends Model
{
    public function marketplaceItems(): HasMany
    {
        return $this->hasMany(MarketplaceItem::class, 'category_id');
    }
}
