<?php

namespace App\Enums;

/**
 * AD-001 قائمة مغلقة أصلًا بثمانية أحداث (Subscription×3, Access×2, Seat×2،
 * الأخيران بلا استخدام فعلي لحد Phase 2B). AD-009 يثبّت التمييز الدلالي:
 * أحداث Seat للمسار المؤسسي، أحداث Access وSubscription تُستخدَم بالمسارين معًا.
 * Phase OL (AD-001 مُعدَّل) أضافت حدثين: OrganizationArchived/Restored.
 * Platform Authorization — Security Hardening Pass أضافت MembershipCreated
 * وOwnershipGranted.
 * Final Execution Sprint — AD-016 أُغلِقت بالكامل الآن: MembershipRoleChanged
 * وMembershipRemoved وOwnershipTransferred تُغطي كل تغيير Domain حساس
 * بعضوية مؤسسة (لم يكن أي منها مُدقَّقًا من قبل). زائد أحداث إفلاس تك
 * (Case*) — أول تطبيق حقيقي يستهلك AuditLog لِتَتبُّع أفعاله الحساسة.
 * المرحلة 1 (النموذج القانوني الكامل) أضافت 8 أحداث جديدة لتغطية الدائنين/
 * الأصول/الموظفين/الجلسات/الجدول الزمني/الملف الشخصي/المعالج/القوائم
 * التنظيمية — كلها بنفس نمط الأحداث الحالية بالضبط.
 */
enum AuditEvent: string
{
    case SubscriptionCreated = 'subscription_created';
    case SubscriptionActivated = 'subscription_activated';
    case SubscriptionCancelled = 'subscription_cancelled';
    case AccessGranted = 'access_granted';
    case AccessRevoked = 'access_revoked';
    case SeatAssigned = 'seat_assigned';
    case SeatReleased = 'seat_released';
    case OrganizationArchived = 'organization_archived';
    case OrganizationRestored = 'organization_restored';
    case MembershipCreated = 'membership_created';
    case OwnershipGranted = 'ownership_granted';
    case MembershipRoleChanged = 'membership_role_changed';
    case MembershipRemoved = 'membership_removed';
    case OwnershipTransferred = 'ownership_transferred';
    case CaseCreated = 'case_created';
    case CaseStatusChanged = 'case_status_changed';
    case CasePartyAdded = 'case_party_added';
    case CaseProcedureAdded = 'case_procedure_added';
    case CaseProcedureStatusChanged = 'case_procedure_status_changed';
    case CaseNoteAdded = 'case_note_added';
    case CaseDocumentUploaded = 'case_document_uploaded';
    case CaseCreditorAdded = 'case_creditor_added';
    case CaseAssetAdded = 'case_asset_added';
    case CaseEmployeeAdded = 'case_employee_added';
    case CaseHearingAdded = 'case_hearing_added';
    case CaseTimelineEventToggled = 'case_timeline_event_toggled';
    case CaseProfileUpdated = 'case_profile_updated';
    case CaseWizardAnswersUpdated = 'case_wizard_answers_updated';
    case CaseChecklistsUpdated = 'case_checklists_updated';
    case CaseClientInvited = 'case_client_invited';
    case CaseClientAccessRevoked = 'case_client_access_revoked';
    case CaseClientAccessRestored = 'case_client_access_restored';
    case CaseSignatureSaved = 'case_signature_saved';
    case CaseDeleted = 'case_deleted';
    case ArticleAuthorRequested = 'article_author_requested';
    case ArticleAuthorApproved = 'article_author_approved';
    case ArticleAuthorRejected = 'article_author_rejected';
    case ArticleCreated = 'article_created';
    case ArticleSubmittedForReview = 'article_submitted_for_review';
    case ArticlePublished = 'article_published';
    case ArticleRejected = 'article_rejected';
    case ServiceListingCreated = 'service_listing_created';
    case ServiceListingClosed = 'service_listing_closed';
    case ServiceListingInquirySubmitted = 'service_listing_inquiry_submitted';
    case TechServiceRequestSubmitted = 'tech_service_request_submitted';
    case TrainingOpportunityCreated = 'training_opportunity_created';
    case TrainingOpportunityClosed = 'training_opportunity_closed';
    case TrainingApplicationSubmitted = 'training_application_submitted';
    case CaseDraftGenerated = 'case_draft_generated';
    case CaseDraftGenerationFailed = 'case_draft_generation_failed';
}
