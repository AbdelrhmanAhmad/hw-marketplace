<?php

namespace App\Support;

use App\Models\BankruptcyCase;

/**
 * محرك التصنيف القانوني الحتمي — نسخ حرفي لترتيب الفروع من recommendation.ts
 * بـhw-eflas، بما فيها حدود الحافة بالضبط (>= مقابل >، < مقابل <=). حساب
 * صرف فقط — لا Gate، لا DB mutation، لا استدعاء AI (ذاك مؤجَّل، خارج المرحلة 1).
 *
 * تحذير: أي تعديل على حدود الأرقام هنا (150_000 / 1_000_000 / 0.3) يجب أن
 * يطابق recommendation.test.ts الأصلي حرفيًا — هذي القيم اختيرت بعناية هناك
 * (اختُبرت صراحة عند نقاط الحدود بالضبط).
 */
class BankruptcyRecommendationEngine
{
    private const int LIQUIDATION_COST_ESTIMATE = 150_000;

    public function recommend(BankruptcyCase $case): BankruptcyRecommendation
    {
        $totalDebts = (float) $case->total_debts;
        $totalAssets = (float) $case->total_assets;
        $assetsCoverLiquidation = $totalAssets >= self::LIQUIDATION_COST_ESTIMATE;
        $isSmallDebtor = $case->employees()->count() <= 5 && $totalDebts < 1_000_000;
        $noAssetsAtAll = $case->has_assets === 'no' || $totalAssets === 0.0;

        if ($case->insolvency_status === 'upcoming') {
            if ($case->operated_twelve_months === 'yes' && $case->previous_settlement === 'no') {
                return new BankruptcyRecommendation(
                    code: 'preventive',
                    title: 'التسوية الوقائية (المادة 71)',
                    reason: 'الإعسار متوقَّع لا فعلي، والمنشأة تستوفي شروط طلب التسوية الوقائية.',
                    articles: ['71 شروط القبول', '73 خطة التسوية', '79 التصويت'],
                );
            }

            $missing = $case->operated_twelve_months !== 'yes'
                ? 'عدم استيفاء شرط مزاولة النشاط 12 شهرًا متتاليًا'
                : 'وجود تسوية سابقة لم تنتهِ مدتها بعد';

            return new BankruptcyRecommendation(
                code: 'needs_review',
                title: 'يتطلب مراجعة قانونية',
                reason: "لا تستوفي المنشأة شروط المادة 71 بعد — {$missing}.",
                articles: [],
            );
        }

        if ($noAssetsAtAll || ! $assetsCoverLiquidation) {
            if ($isSmallDebtor) {
                return new BankruptcyRecommendation(
                    code: 'admin',
                    title: 'التصفية الإدارية — المسار المبسّط للمنشآت الصغيرة (المادة 168/أ)',
                    reason: 'الأصول لا تكفي مصروفات إجراء التصفية، والمنشأة تستوفي معايير المنشآت الصغيرة (عدد الموظفين والديون) المؤهِّلة للمسار المبسَّط.',
                    articles: ['168/أ', '7'],
                );
            }

            return new BankruptcyRecommendation(
                code: 'admin',
                title: 'التصفية الإدارية (المادة 168)',
                reason: 'الأصول لا تكفي لتغطية مصروفات إجراء التصفية المقدَّرة.',
                articles: ['168', '170'],
            );
        }

        if ($case->is_active === 'yes' && $totalAssets > $totalDebts * 0.3) {
            return new BankruptcyRecommendation(
                code: 'restructuring',
                title: 'إعادة الهيكلة (المادة 83)',
                reason: 'المنشأة نشطة وأصولها تفوق 30% من ديونها — تستوفي شروط إعادة الهيكلة.',
                articles: ['83', '85', '92'],
            );
        }

        return new BankruptcyRecommendation(
            code: 'regular',
            title: 'التصفية العادية (المادة 101)',
            reason: 'إعسار فعلي لا يستوفي شروط أي مسار آخر — التصفية العادية هي الإجراء المناسب.',
            articles: ['101', '103', '110'],
        );
    }

    /**
     * إصلاح خلل حقيقي أبلغ عنه المستخدم: recommend() كانت تُحسَب دائمًا حتى
     * على قضية جديدة فارغة تمامًا — القيم الافتراضية الفارغة (`has_assets`
     * غير مُجاب = يُعامَل ضمنيًا كـ"لا أصول") تُنتج توصية "تصفية إدارية" فورية
     * بلا أي معنى حقيقي. لا تُعرَض أي توصية قبل اكتمال الأسئلة العشرة كاملة.
     */
    public function isReadyForRecommendation(BankruptcyCase $case): bool
    {
        foreach ([
            'is_establishment', 'is_active', 'has_assets', 'assets_cover_expenses', 'insolvency_status',
            'financial_statements_available', 'financial_transactions_available', 'creditors_notified',
            'operated_twelve_months', 'previous_settlement',
        ] as $field) {
            if ($case->$field === null || $case->$field === '') {
                return false;
            }
        }

        return true;
    }

    /** @return BankruptcyDeficiency[] */
    public function deficiencies(BankruptcyCase $case): array
    {
        $items = [];

        if (mb_strlen((string) $case->cr_number) !== 10) {
            $items[] = new BankruptcyDeficiency('critical', 'رقم السجل التجاري يجب أن يتكون من 10 أرقام كاملة.');
        }

        // mb_strlen وليس strlen — الأحرف العربية متعددة البايت بـUTF-8،
        // strlen كان سيعطي نتيجة خاطئة تمامًا لأسماء عربية حقيقية.
        if (mb_strlen(trim((string) $case->attorney_name)) <= 3) {
            $items[] = new BankruptcyDeficiency('critical', 'يجب إدخال اسم المحامي الوكيل ورقم رخصة المحاماة.');
        }

        if ((float) $case->total_assets <= 0) {
            $items[] = new BankruptcyDeficiency('critical', 'إجمالي الأصول يجب أن يكون أكبر من صفر.');
        }

        if ((float) $case->total_debts <= 0) {
            $items[] = new BankruptcyDeficiency('critical', 'إجمالي الديون يجب أن يكون أكبر من صفر.');
        }

        // قرار الشركاء مطلوب للشركات فقط — لا يوجد عمود تصنيف مستندات مخصَّص
        // بالمرحلة 1، لذا يُطابَق عنوان المستند نصيًا (نفس أسلوب البحث
        // الوحيد المتاح حاليًا؛ لو أُضيف تصنيف مستندات لاحقًا يُستبدَل هذا).
        if ($case->is_establishment === 'company') {
            $hasResolution = $case->documents()->where('title', 'like', '%قرار الشركاء%')->exists();
            if (! $hasResolution) {
                $items[] = new BankruptcyDeficiency('critical', 'يجب إرفاق محضر قرار الشركاء (مطلوب للشركات فقط).');
            }
        }

        if ($case->financial_statements_available !== 'yes') {
            $items[] = new BankruptcyDeficiency('critical', 'القوائم المالية لآخر سنتين غير متوفرة (أو خطاب اعتذار مرفق).');
        }

        if ($case->financial_transactions_available !== 'yes') {
            $items[] = new BankruptcyDeficiency('warning', 'كشف المعاملات المالية لآخر 24 شهرًا غير متوفر.');
        }

        if ($case->creditors_notified !== 'yes') {
            $items[] = new BankruptcyDeficiency('warning', 'لم يتم إخطار الدائنين بعد.');
        }

        $zatca = $case->zatca_checklist ?? [];
        $zatcaChecked = count(array_filter($zatca));
        if ($zatcaChecked < 4) {
            $remaining = 4 - $zatcaChecked;
            $items[] = new BankruptcyDeficiency('warning', "قائمة زكاة وضريبة وجمارك ناقصة — {$remaining} بند(ود) متبقية.");
        }

        return $items;
    }
}
