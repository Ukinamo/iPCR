<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sto_monitoring_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->string('report_type');
            $table->string('title');
            $table->string('office_name')->default('CHEDRO : MIMAROPA');
            $table->unsignedSmallInteger('evaluation_year');
            $table->timestamps();
        });

        Schema::create('sto_monitoring_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sto_monitoring_form_id')->constrained('sto_monitoring_forms')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('hei_name')->nullable();
            $table->string('monitored_item')->nullable();
            $table->unsignedInteger('grantee_count')->nullable();
            $table->string('date_monitored')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sto_monitoring_entries');
        Schema::dropIfExists('sto_monitoring_forms');
    }
};
