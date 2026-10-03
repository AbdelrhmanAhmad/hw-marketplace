<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * بوابة المقالات — الكيان الجذري. لا Authorization هنا إطلاقًا —
 * ArticleService وحدها تقرر (نفس نمط BankruptcyCase تمامًا، BR-013).
 * body نص عادي (لا Markdown/HTML) — يُعرَض بـwhite-space:pre-line.
 */
#[Fillable([
    'article_author_id', 'article_category_id', 'title', 'slug', 'excerpt', 'body',
    'cover_image_path', 'status', 'rejection_reason', 'published_at',
])]
class Article extends Model
{
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(ArticleAuthor::class, 'article_author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function coverImageUrl(): ?string
    {
        return $this->cover_image_path ? Storage::disk('public')->url($this->cover_image_path) : null;
    }

    /**
     * تقدير بسيط لوقت القراءة — 200 كلمة/دقيقة. تقسيم بالمسافات صراحة
     * (لا str_word_count) — تلك تعتمد على حروف a-zA-Z فقط افتراضيًا وترجع
     * صفرًا لنص عربي بالكامل.
     */
    public function readingMinutes(): int
    {
        $wordCount = count(array_filter(preg_split('/\s+/u', trim($this->body)) ?: []));

        return max(1, (int) ceil($wordCount / 200));
    }
}
