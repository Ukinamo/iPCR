<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('evaluation_year');
            $table->unsignedTinyInteger('evaluation_quarter');
            $table->string('status', 32)->default('pending');
            $table->unsignedTinyInteger('quality')->nullable();
            $table->unsignedTinyInteger('efficiency')->nullable();
            $table->unsignedTinyInteger('timeliness')->nullable();
            $table->decimal('overall_rating', 4, 2)->nullable();
            $table->text('supervisor_feedback')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // MySQL identifier limit is 64 chars; Laravel's auto-generated unique name exceeds it.
            $table->unique(['employee_id', 'evaluation_year', 'evaluation_quarter'], 'ipcr_submissions_emp_period_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_submissions');
    }
};
