<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_submissions', function (Blueprint $table) {
            $table->index('employee_id', 'ipcr_submissions_employee_id_idx');
        });

        Schema::table('ipcr_submissions', function (Blueprint $table) {
            $table->dropUnique('ipcr_submissions_emp_period_uniq');
        });

        Schema::table('ipcr_submissions', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('supervisor_id');
            $table->string('title')->nullable()->after('batch_id');
            $table->foreignId('ipcr_form_template_id')
                ->nullable()
                ->after('title')
                ->constrained('ipcr_form_templates')
                ->nullOnDelete();
        });

        DB::table('ipcr_submissions')->orderBy('id')->each(function ($submission) {
            $commitment = DB::table('commitments')
                ->where('ipcr_submission_id', $submission->id)
                ->orderBy('id')
                ->first();

            $batchId = $commitment->batch_id ?? (string) Str::uuid();

            DB::table('ipcr_submissions')->where('id', $submission->id)->update([
                'batch_id' => $batchId,
                'title' => $submission->title ?: 'IPCR package',
                'ipcr_form_template_id' => $commitment->ipcr_form_template_id ?? null,
            ]);

            DB::table('commitments')
                ->where('ipcr_submission_id', $submission->id)
                ->whereNull('batch_id')
                ->update(['batch_id' => $batchId]);
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ipcr_form_template_id');
            $table->dropColumn(['batch_id', 'title']);
            $table->unique(
                ['employee_id', 'evaluation_year', 'evaluation_quarter'],
                'ipcr_submissions_emp_period_uniq',
            );
        });
    }
};
