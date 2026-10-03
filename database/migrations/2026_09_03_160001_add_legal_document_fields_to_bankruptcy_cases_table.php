<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إفلاس تك — المرحلة 3 (توليد المستندات القانونية). هذي الحقول استُبعدت
 * عمدًا بالمرحلة 1 (بلا فائدة بدون توليد مستندات) — أصبح لها استخدام حقيقي
 * الآن. التوقيعان صور PNG (base64) حقيقية يرسمها المستخدم بـCanvas — لا
 * علاقة لهما بأي تحقق هوية حكومي (لا بوابة Nafath وهمية، قرار مرفوض عمدًا).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bankruptcy_cases', function (Blueprint $table) {
            $table->date('document_date')->nullable();
            $table->string('document_time')->nullable();
            $table->string('poa_number')->nullable();
            $table->date('poa_date')->nullable();
            $table->string('poa_city')->nullable();
            $table->text('lawyer_signature_data')->nullable();
            $table->text('representative_signature_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bankruptcy_cases', function (Blueprint $table) {
            $table->dropColumn([
                'document_date', 'document_time', 'poa_number', 'poa_date', 'poa_city',
                'lawyer_signature_data', 'representative_signature_data',
            ]);
        });
    }
};
