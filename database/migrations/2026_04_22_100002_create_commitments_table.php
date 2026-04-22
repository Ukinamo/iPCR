<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ipcr_submission_id')->nullable()->constrained('ipcr_submissions')->nullOnDelete();
            $table->unsignedSmallInteger('evaluation_year');
            $table->unsignedTinyInteger('evaluation_quarter');
            $table->string('period_label', 32);
            $table->string('title');
            $table->string('function_type', 32);
            $table->decimal('weight', 5, 2);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('status', 32)->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commitments');
    }
};
