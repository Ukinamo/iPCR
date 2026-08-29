<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_form_templates', function (Blueprint $table) {
            $table->json('included_quarters')->nullable()->after('evaluation_quarter');
        });

        Schema::table('ipcr_submissions', function (Blueprint $table) {
            $table->json('included_quarters')->nullable()->after('evaluation_quarter');
        });

        Schema::table('commitments', function (Blueprint $table) {
            $table->decimal('rating_q1_target', 14, 4)->nullable()->after('rating_target_total');
            $table->decimal('rating_q1_actual', 14, 4)->nullable()->after('rating_q1_target');
            $table->decimal('rating_q2_target', 14, 4)->nullable()->after('rating_q1_actual');
            $table->decimal('rating_q2_actual', 14, 4)->nullable()->after('rating_q2_target');
        });

        $default = json_encode([3, 4]);
        DB::table('ipcr_form_templates')->whereNull('included_quarters')->update(['included_quarters' => $default]);
        DB::table('ipcr_submissions')->whereNull('included_quarters')->update(['included_quarters' => $default]);
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->dropColumn([
                'rating_q1_target',
                'rating_q1_actual',
                'rating_q2_target',
                'rating_q2_actual',
            ]);
        });

        Schema::table('ipcr_submissions', function (Blueprint $table) {
            $table->dropColumn('included_quarters');
        });

        Schema::table('ipcr_form_templates', function (Blueprint $table) {
            $table->dropColumn('included_quarters');
        });
    }
};
