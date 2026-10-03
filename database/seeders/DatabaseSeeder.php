<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database using Eloquent model seeders + JSON fixtures.
     *
     * Order respects foreign keys. Catalog/tests may still call MarketplaceCatalogSeeder
     * directly; the default path loads the full local baseline fixtures.
     */
    public function run(): void
    {
        $this->call([
            // Identity
            UserSeeder::class,

            // Marketplace catalog
            PartnerSeeder::class,
            MarketplaceCategorySeeder::class,
            MarketplaceItemSeeder::class,
            ApplicationDetailSeeder::class,

            // Marefa / knowledge
            CategorySeeder::class,
            LawEntrySeeder::class,
            LawArticleSeeder::class,
            CategoryLawEntrySeeder::class,
            LegalUpdateSeeder::class,
            BookmarkSeeder::class,

            // Organizations & access
            OrganizationSeeder::class,
            MembershipSeeder::class,
            SubscriptionPlanSeeder::class,
            SubscriptionSeeder::class,
            SubscriptionSeatSeeder::class,
            AccessAssignmentSeeder::class,
            AppSubscriptionSeeder::class,

            // Articles portal
            ArticleCategorySeeder::class,
            ArticleAuthorSeeder::class,
            ArticleSeeder::class,

            // Tech portal
            TechServiceSeeder::class,
            TechServiceRequestSeeder::class,
            TechServiceRequestItemSeeder::class,

            // Community
            ServiceInterestSeeder::class,
            ServiceListingSeeder::class,
            ServiceListingInquirySeeder::class,

            // Training / internships
            TrainingOpportunitySeeder::class,
            TrainingApplicationSeeder::class,
            TrainingApplicationDocumentSeeder::class,

            // Bankruptcy Tech
            BankruptcyCaseSeeder::class,
            CasePartySeeder::class,
            CaseProcedureSeeder::class,
            CaseDocumentSeeder::class,
            CaseNoteSeeder::class,
            CaseCreditorSeeder::class,
            CaseAssetSeeder::class,
            CaseEmployeeSeeder::class,
            CaseHearingSeeder::class,
            CaseTimelineEventSeeder::class,
            CaseDraftGenerationSeeder::class,

            // Audit (append-only insert)
            AuditLogSeeder::class,
        ]);
    }
}
