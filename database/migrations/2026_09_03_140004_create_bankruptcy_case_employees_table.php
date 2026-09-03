<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لا عمود `benefits`/EOSB هنا عمدًا (قرار #4 بخطة المرحلة 1) — hw-eflas
 * يخزّنه لكن لا يثق فيه أبدًا ويعيد حسابه حيًا دائمًا؛ هنا لا يُخزَّن إطلاقًا،
 * `CaseEmployee::eosb()` accessor يحسبه حيًا عبر `Eosb::calculate()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bankruptcy_case_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bankruptcy_case_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('nationality');
            $table->string('iqama');
            $table->decimal('salary', 12, 2);
            $table->date('join_date');
            $table->foreignId('added_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('bankruptcy_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bankruptcy_case_employees');
    }
};
