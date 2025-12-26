<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('products', 'sku')) {
            Schema::table('products', function (Blueprint $table) {
                // drop unique index if exists
                try {
                    $table->dropUnique(['sku']);
                } catch (\Throwable $e) {
                    // ignore if index doesn't exist
                }

                $table->dropColumn('sku');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable()->unique()->after('id');
        });
    }
};
