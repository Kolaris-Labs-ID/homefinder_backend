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
        Schema::table('listings', function (Blueprint $table) {
            if (!Schema::hasColumn('listings', 'price_max')) {
                $table->bigInteger('price_max')->nullable()->after('price');
            }
            if (!Schema::hasColumn('listings', 'is_price_range')) {
                $table->boolean('is_price_range')->default(false)->after('price_max');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['price_max', 'is_price_range']);
        });
    }
};
