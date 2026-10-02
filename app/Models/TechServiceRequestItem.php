<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tech_service_request_id', 'tech_service_id'])]
class TechServiceRequestItem extends Model
{
    public function request(): BelongsTo
    {
        return $this->belongsTo(TechServiceRequest::class, 'tech_service_request_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(TechService::class, 'tech_service_id');
    }
}
