<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الجدول الزمني القانوني الثابت (8 مراحل نظامية من hw-eflas، بفارق أيام عن
 * submission_date) — مختلف تمامًا عن تبويب "سجل الأحداث" الحالي (مبني على
 * AuditLog). تُزرَع الصفوف الثمانية تلقائيًا داخل BankruptcyCaseService::
 * createCase()، والحقل الوحيد القابل للتعديل لاحقًا هو `done`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bankruptcy_case_timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bankruptcy_case_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('day_offset');
            $table->string('category'); // critical|warning|info
            $table->boolean('done')->default(false);
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->index('bankruptcy_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bankruptcy_case_timeline_events');
    }
};
