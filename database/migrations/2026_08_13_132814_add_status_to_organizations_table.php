<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase OL — Active/Archived فقط. حقل حالة صريح (قرار المستخدم المباشر)،
     * لا منطق مشتق من وجود بيانات أخرى. راجع
     * docs/phase-ol-implementation-specification.md.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('status')->default('active')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
