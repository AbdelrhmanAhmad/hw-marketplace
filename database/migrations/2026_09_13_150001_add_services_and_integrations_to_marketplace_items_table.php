<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('marketplace_items', function (Blueprint $table) {
            // نفس نمط compatibility (json nullable) — قائمة خدمات محددة يقدّمها
            // العنصر، وقائمة الجهات/المنصات الخارجية التي يُبنى عليها الربط.
            $table->json('services')->nullable()->after('compatibility');
            $table->json('integrations')->nullable()->after('services');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_items', function (Blueprint $table) {
            $table->dropColumn(['services', 'integrations']);
        });
    }
};
