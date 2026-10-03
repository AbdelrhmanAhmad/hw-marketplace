<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تخزين حقيقي (Laravel Storage، القرص الخاص `local`) — لا Mock. الملف نفسه
 * لا يُخدَّم مباشرة أبدًا (لا Public Disk) — يمر دائمًا عبر Controller يتحقق
 * من BankruptcyCasePolicy قبل السماح بالتنزيل (لا تسريب عبر رابط تخمين).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bankruptcy_case_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bankruptcy_case_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('original_filename');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('bankruptcy_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bankruptcy_case_documents');
    }
};
