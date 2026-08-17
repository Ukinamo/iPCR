<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_form_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('evaluation_year');
            $table->unsignedTinyInteger('evaluation_quarter');
            $table->string('period_label', 32);
            $table->string('title')->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['supervisor_id', 'evaluation_year', 'evaluation_quarter'],
                'ipcr_form_templates_supervisor_period_uniq',
            );
        });

        Schema::create('ipcr_form_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_form_template_id')->constrained('ipcr_form_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('function_type', 32);
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->string('annual_office_target', 255)->nullable();
            $table->string('individual_annual_targets', 255)->nullable();
            $table->timestamps();
        });

        Schema::table('commitments', function (Blueprint $table) {
            $table->foreignId('ipcr_form_template_id')
                ->nullable()
                ->after('batch_id')
                ->constrained('ipcr_form_templates')
                ->nullOnDelete();
            $table->foreignId('ipcr_form_template_item_id')
                ->nullable()
                ->after('ipcr_form_template_id')
                ->constrained('ipcr_form_template_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ipcr_form_template_item_id');
            $table->dropConstrainedForeignId('ipcr_form_template_id');
        });

        Schema::dropIfExists('ipcr_form_template_items');
        Schema::dropIfExists('ipcr_form_templates');
    }
};
