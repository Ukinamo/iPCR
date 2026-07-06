<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_submissions', function (Blueprint $table) {
            $table->text('commitment_statement')->nullable()->after('supervisor_feedback');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_submissions', function (Blueprint $table) {
            $table->dropColumn('commitment_statement');
        });
    }
};
