<?php

namespace App\Http\Requests\Concerns;

/**
 * إصلاح خلل حقيقي (الجزء الثاني): backToCaseTab بالـController يعالج
 * الأخطاء المرمية يدويًا (InvalidArgumentException) فقط. لكن فشل تحقق
 * FormRequest التلقائي (حقل مطلوب فارغ، صيغة خاطئة...) يرمي
 * ValidationException **قبل الوصول للـController إطلاقًا** — Laravel
 * يستخدم `back()`/`url()->previous()` افتراضيًا لهذا المسار، بمعزل تام عن أي
 * منطق كتبناه بالـController. هذا التريت يجعل FormRequest نفسه يعرف يرجع
 * لنفس التبويب دائمًا، بغضّ النظر عن نوع فشل التحقق.
 */
trait RedirectsToCaseTab
{
    // ملاحظة PHP: لا نعلن $caseTab هنا بقيمة افتراضية — PHP يرفض (Fatal
    // Error) أي كلاس يستخدم هذا الـTrait ثم يُعيد إعلان نفس الخاصية بقيمة
    // مختلفة ("defines the same property... considered incompatible").
    // كل كلاس مستهلك **يجب** يُعلن `protected string $caseTab = '...';` بنفسه.

    protected function getRedirectUrl(): string
    {
        $case = $this->route('case');

        if ($case) {
            return route('bankruptcy-tech.cases.show', $case).'#'.$this->caseTab;
        }

        return parent::getRedirectUrl();
    }
}
