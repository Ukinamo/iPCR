<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->string('annual_office_target', 255)->nullable()->after('weight');
            $table->string('individual_annual_targets', 255)->nullable()->after('annual_office_target');
            $table->string('remarks', 255)->nullable()->after('rating_weighted');
        });
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->dropColumn([
                'annual_office_target',
                'individual_annual_targets',
                'remarks',
            ]);
        });
    }
};
