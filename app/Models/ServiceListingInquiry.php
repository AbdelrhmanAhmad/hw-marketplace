<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_listing_id', 'user_id', 'name', 'email', 'phone', 'message'])]
class ServiceListingInquiry extends Model
{
    public function listing(): BelongsTo
    {
        return $this->belongsTo(ServiceListing::class, 'service_listing_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
