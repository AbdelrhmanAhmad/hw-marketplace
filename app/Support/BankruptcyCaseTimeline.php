<?php

namespace App\Support;

use App\Models\AuditLog;

/**
 * ترجمة أحداث AuditLog الخاصة بإفلاس تك لجُمَل عربية قابلة للعرض بالسجل
 * الزمني — طبقة عرض بحتة، لا تُعيد تفسير الحدث أو تُغيّر معناه.
 */
class BankruptcyCaseTimeline
{
    public static function describe(AuditLog $entry): string
    {
        return match ($entry->event) {
            'case_created' => 'أُنشئت القضية: "'.($entry->metadata['title'] ?? '').'"',
            'case_status_changed' => 'تغيّرت حالة القضية من "'.self::statusLabel($entry->metadata['from'] ?? null).'" إلى "'.self::statusLabel($entry->metadata['to'] ?? null).'"',
            'case_party_added' => 'أُضيف طرف: '.($entry->metadata['name'] ?? ''),
            'case_procedure_added' => 'أُضيف إجراء: '.($entry->metadata['title'] ?? ''),
            'case_procedure_status_changed' => 'تغيّرت حالة إجراء من "'.($entry->metadata['from'] ?? '').'" إلى "'.($entry->metadata['to'] ?? '').'"',
            'case_note_added' => 'أُضيفت ملاحظة جديدة',
            'case_document_uploaded' => 'رُفع مستند: '.($entry->metadata['filename'] ?? ''),
            'case_creditor_added' => 'أُضيف دائن: '.($entry->metadata['name'] ?? '').' ('.number_format((float) ($entry->metadata['amount'] ?? 0), 2).' ر.س)',
            'case_asset_added' => 'أُضيف أصل: '.($entry->metadata['name'] ?? ''),
            'case_employee_added' => 'أُضيف موظف: '.($entry->metadata['name'] ?? ''),
            'case_hearing_added' => 'أُضيفت جلسة بتاريخ '.($entry->metadata['date'] ?? ''),
            'case_timeline_event_toggled' => (($entry->metadata['done'] ?? false) ? 'أُنجزت مرحلة: ' : 'أُعيدت مرحلة لغير منجزة: ').($entry->metadata['label'] ?? ''),
            'case_profile_updated' => 'حُدِّثت بيانات الملف',
            'case_wizard_answers_updated' => 'حُدِّثت إجابات معالج التشخيص',
            'case_checklists_updated' => 'حُدِّثت القوائم التنظيمية',
            'case_client_invited' => 'دُعي العميل: '.($entry->metadata['client_email'] ?? ''),
            'case_client_access_revoked' => 'أُلغي وصول العميل',
            'case_client_access_restored' => 'استُعيد وصول العميل',
            default => $entry->event,
        };
    }

    private static function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'مسودة',
            'preparing' => 'قيد الإعداد',
            'submitted' => 'مُقدَّمة للمحكمة',
            'decided' => 'صدر قرار',
            'closed' => 'مغلقة',
            default => $status ?? '—',
        };
    }
}
