<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->decimal('rating_q3_target', 14, 4)->nullable()->after('rating_target_total');
            $table->decimal('rating_q3_actual', 14, 4)->nullable()->after('rating_q3_target');
            $table->decimal('rating_q4_target', 14, 4)->nullable()->after('rating_q3_actual');
            $table->decimal('rating_q4_actual', 14, 4)->nullable()->after('rating_q4_target');
            $table->decimal('rating_percent', 14, 6)->nullable()->after('rating_q4_actual');
        });
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->dropColumn([
                'rating_q3_target',
                'rating_q3_actual',
                'rating_q4_target',
                'rating_q4_actual',
                'rating_percent',
            ]);
        });
    }
};
