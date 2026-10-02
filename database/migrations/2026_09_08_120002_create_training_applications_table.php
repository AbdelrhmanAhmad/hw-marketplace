<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بوابة التدريب التعاوني — تقديم طالب على فرصة. user_id إلزامي (بعكس
 * service_listing_inquiries) — التقديم يتطلب حسابًا مسجَّلًا دائمًا، لا زائر ضيف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('message')->nullable();
            $table->string('status')->default('submitted'); // submitted|reviewed|accepted|rejected
            $table->timestamps();

            $table->unique(['training_opportunity_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_applications');
    }
};
