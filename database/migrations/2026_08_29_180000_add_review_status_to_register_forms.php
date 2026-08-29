<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_evaluation_forms', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('evaluation_year');
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->foreignId('reviewer_id')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable()->after('reviewer_id');
        });

        Schema::table('sto_monitoring_forms', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('evaluation_year');
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->foreignId('reviewer_id')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable()->after('reviewer_id');
        });
    }

    public function down(): void
    {
        Schema::table('program_evaluation_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewer_id');
            $table->dropColumn(['status', 'submitted_at', 'reviewed_at', 'review_notes']);
        });

        Schema::table('sto_monitoring_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewer_id');
            $table->dropColumn(['status', 'submitted_at', 'reviewed_at', 'review_notes']);
        });
    }
};
