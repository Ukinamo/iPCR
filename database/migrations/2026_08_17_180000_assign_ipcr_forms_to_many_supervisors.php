<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ipcr_form_template_supervisors')) {
            Schema::create('ipcr_form_template_supervisors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ipcr_form_template_id')->constrained('ipcr_form_templates')->cascadeOnDelete();
                $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['ipcr_form_template_id', 'supervisor_id'],
                    'ipcr_form_template_supervisor_uniq',
                );
            });
        }

        if (Schema::hasColumn('ipcr_form_templates', 'supervisor_id')) {
            $templates = DB::table('ipcr_form_templates')
                ->whereNotNull('supervisor_id')
                ->get(['id', 'supervisor_id', 'assigned_at', 'created_at', 'updated_at']);

            foreach ($templates as $template) {
                $exists = DB::table('ipcr_form_template_supervisors')
                    ->where('ipcr_form_template_id', $template->id)
                    ->where('supervisor_id', $template->supervisor_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('ipcr_form_template_supervisors')->insert([
                    'ipcr_form_template_id' => $template->id,
                    'supervisor_id' => $template->supervisor_id,
                    'assigned_at' => $template->assigned_at,
                    'created_at' => $template->created_at ?? now(),
                    'updated_at' => $template->updated_at ?? now(),
                ]);
            }

            Schema::table('ipcr_form_templates', function (Blueprint $table) {
                $table->dropForeign(['supervisor_id']);
            });

            $indexExists = collect(Schema::getIndexes('ipcr_form_templates'))
                ->contains(fn ($index) => ($index['name'] ?? null) === 'ipcr_form_templates_supervisor_period_uniq');

            if ($indexExists) {
                Schema::table('ipcr_form_templates', function (Blueprint $table) {
                    $table->dropUnique('ipcr_form_templates_supervisor_period_uniq');
                });
            }

            Schema::table('ipcr_form_templates', function (Blueprint $table) {
                $table->dropColumn('supervisor_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ipcr_form_templates', 'supervisor_id')) {
            Schema::table('ipcr_form_templates', function (Blueprint $table) {
                $table->foreignId('supervisor_id')->nullable()->after('created_by')->constrained('users')->cascadeOnDelete();
            });
        }

        $assignments = DB::table('ipcr_form_template_supervisors')
            ->orderBy('id')
            ->get(['ipcr_form_template_id', 'supervisor_id']);

        foreach ($assignments as $assignment) {
            DB::table('ipcr_form_templates')
                ->where('id', $assignment->ipcr_form_template_id)
                ->whereNull('supervisor_id')
                ->update(['supervisor_id' => $assignment->supervisor_id]);
        }

        Schema::table('ipcr_form_templates', function (Blueprint $table) {
            $table->unique(
                ['supervisor_id', 'evaluation_year', 'evaluation_quarter'],
                'ipcr_form_templates_supervisor_period_uniq',
            );
        });

        Schema::dropIfExists('ipcr_form_template_supervisors');
    }
};
