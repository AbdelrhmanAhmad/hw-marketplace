<?php

namespace App\Http\Controllers\BankruptcyTech\Concerns;

use App\Models\BankruptcyCase;
use Illuminate\Http\RedirectResponse;

/**
 * إصلاح خلل حقيقي أبلغ عنه المستخدم: كل نموذج بصفحة القضية (Wizard/الدائنون/
 * القوائم...) كان يرسل POST/PATCH ثم `back()` — Laravel يعيد تحميل الصفحة
 * بالكامل، وAlpine.js يبدأ دائمًا من تبويب "نظرة عامة" لأنه لا يحفظ أي حالة.
 * النتيجة: كل حفظ (ناجح أو فاشل بخطأ تحقق) يرمي المستخدم لتبويب مختلف تمامًا
 * عن اللي كان يعمل فيه — بما فيها رسائل الخطأ اللي تظهر ببانر بلا سياق ظاهر.
 *
 * الحل: كل تحويل (نجاح أو خطأ) يتضمّن #fragment باسم التبويب — show.blade.php
 * يقرأ هذا الـfragment عند التحميل ليبدأ من نفس التبويب مباشرة (JS، لا يحتاج
 * تغيير بالخادم غير إضافة الـfragment بالتحويل نفسه).
 */
trait RedirectsToCaseTab
{
    protected function backToCaseTab(BankruptcyCase $case, string $tab): RedirectResponse
    {
        return redirect(route('bankruptcy-tech.cases.show', $case).'#'.$tab);
    }
}
