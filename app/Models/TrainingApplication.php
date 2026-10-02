<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['training_opportunity_id', 'user_id', 'message', 'status'])]
class TrainingApplication extends Model
{
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(TrainingOpportunity::class, 'training_opportunity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TrainingApplicationDocument::class);
    }
}
