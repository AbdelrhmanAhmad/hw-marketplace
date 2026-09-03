<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * إفلاس تك — الكيان الجذري. `organization_id=null` = قضية شخصية (نفس نمط
 * `Subscription.subscriber` — مسار مزدوج شخصي/مؤسسي). لا Authorization هنا
 * إطلاقًا — `BankruptcyCasePolicy` وحدها تقرر من يرى/يعدّل ماذا (AD-012).
 * كل تعديل حالة/محتوى يمر عبر BankruptcyCaseService حصرًا — لا
 * Model::update() مباشر من Controller/Filament (نفس BR-013 المُطبَّق على
 * كامل Marketplace Domain).
 */
#[Fillable([
    'case_number', 'court_case_number', 'organization_id', 'created_by_user_id', 'title', 'description', 'status',
    'opened_at', 'closed_at', 'debtor_name', 'legal_form', 'cr_number', 'cr_city', 'court_city',
    'representative_name', 'representative_title', 'representative_id', 'attorney_name', 'attorney_license',
    'submission_date', 'trustee_name',
    'is_establishment', 'is_active', 'has_assets', 'assets_cover_expenses', 'insolvency_status',
    'financial_statements_available', 'financial_transactions_available', 'creditors_notified',
    'operated_twelve_months', 'previous_settlement',
    'zatca_file_number', 'zatca_checklist', 'gosi_file_number', 'gosi_checklist', 'hr_checklist',
    'commerce_cr_cancellation_requested', 'sama_notified',
    'client_user_id', 'client_access_revoked_at',
    'document_date', 'document_time', 'poa_number', 'poa_date', 'poa_city',
    'lawyer_signature_data', 'representative_signature_data',
])]
class BankruptcyCase extends Model
{
    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'closed_at' => 'date',
            'submission_date' => 'date',
            'document_date' => 'date',
            'poa_date' => 'date',
            'zatca_checklist' => 'array',
            'gosi_checklist' => 'array',
            'hr_checklist' => 'array',
            'commerce_cr_cancellation_requested' => 'boolean',
            'sama_notified' => 'boolean',
            'client_access_revoked_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** عميل القضية (المدين) — المرحلة 2، مستقل تمامًا عن Membership/Organization. */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(CaseParty::class);
    }

    public function procedures(): HasMany
    {
        return $this->hasMany(CaseProcedure::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CaseDocument::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CaseNote::class);
    }

    public function creditors(): HasMany
    {
        return $this->hasMany(CaseCreditor::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(CaseAsset::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(CaseEmployee::class);
    }

    public function hearings(): HasMany
    {
        return $this->hasMany(CaseHearing::class);
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(CaseTimelineEvent::class)->orderBy('sort_order');
    }

    public function isPersonal(): bool
    {
        return $this->organization_id === null;
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function hasActiveClient(): bool
    {
        return $this->client_user_id !== null && $this->client_access_revoked_at === null;
    }

    /**
     * محسوب حيًا من sum(creditors.amount) — لا عمود مخزَّن (قرار #3 بخطة
     * المرحلة 1)، يمنع أي احتمال Drift بين القيمة المعروضة والدائنين الفعليين.
     */
    protected function totalDebts(): Attribute
    {
        return Attribute::get(fn () => (float) $this->creditors()->sum('amount'));
    }

    /** محسوب حيًا من sum(assets.value) — نفس منطق totalDebts أعلاه. */
    protected function totalAssets(): Attribute
    {
        return Attribute::get(fn () => (float) $this->assets()->sum('value'));
    }
}
