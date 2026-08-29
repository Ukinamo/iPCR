<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_evaluation_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->default('Programs Monitored/Evaluated/Inspected');
            $table->string('office_name')->default('CHEDRO : MIMAROPA');
            $table->unsignedSmallInteger('evaluation_year');
            $table->timestamps();
        });

        Schema::create('program_evaluation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_evaluation_form_id')->constrained('program_evaluation_forms')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('institutional_code')->nullable();
            $table->string('hei_name')->nullable();
            $table->string('institutional_type')->nullable();
            $table->string('program_name')->nullable();
            $table->unsignedSmallInteger('program_count')->nullable();
            $table->string('purpose')->nullable();
            $table->string('effectivity_ay')->nullable();
            $table->string('date_applied')->nullable();
            $table->string('date_evaluated')->nullable();
            $table->string('result')->nullable();
            $table->text('final_recommendation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_evaluation_entries');
        Schema::dropIfExists('program_evaluation_forms');
    }
};
