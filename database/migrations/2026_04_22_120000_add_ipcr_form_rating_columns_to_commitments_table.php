<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->decimal('rating_actual_total', 14, 4)->nullable()->after('progress');
            $table->decimal('rating_target_total', 14, 4)->nullable()->after('rating_actual_total');
            $table->unsignedTinyInteger('rating_quality')->nullable()->after('rating_target_total');
            $table->unsignedTinyInteger('rating_efficiency')->nullable()->after('rating_quality');
            $table->unsignedTinyInteger('rating_timeliness')->nullable()->after('rating_efficiency');
            $table->decimal('rating_average', 8, 4)->nullable()->after('rating_timeliness');
            $table->decimal('rating_weighted', 10, 6)->nullable()->after('rating_average');
        });
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->dropColumn([
                'rating_actual_total',
                'rating_target_total',
                'rating_quality',
                'rating_efficiency',
                'rating_timeliness',
                'rating_average',
                'rating_weighted',
            ]);
        });
    }
};
