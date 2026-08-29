<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_form_template_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('function_group')->default(0)->after('sort_order');
        });

        Schema::table('commitments', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('function_type');
            $table->unsignedSmallInteger('function_group')->default(0)->after('sort_order');
        });

        $this->backfillTemplateItems();
        $this->backfillCommitments();
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'function_group']);
        });

        Schema::table('ipcr_form_template_items', function (Blueprint $table) {
            $table->dropColumn('function_group');
        });
    }

    private function backfillTemplateItems(): void
    {
        $items = DB::table('ipcr_form_template_items')
            ->orderBy('ipcr_form_template_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'ipcr_form_template_id', 'function_type', 'title']);

        $group = 0;
        $prev = null;
        foreach ($items as $item) {
            $sameParent = $prev && (int) $prev->ipcr_form_template_id === (int) $item->ipcr_form_template_id;
            $sameFn = $sameParent
                && $prev->function_type === $item->function_type
                && (string) ($prev->title ?? '') === (string) ($item->title ?? '');
            if (! $sameFn) {
                $group = $sameParent ? $group + 1 : 0;
            }
            DB::table('ipcr_form_template_items')->where('id', $item->id)->update(['function_group' => $group]);
            $prev = $item;
        }
    }

    private function backfillCommitments(): void
    {
        $rows = DB::table('commitments')
            ->orderBy('ipcr_submission_id')
            ->orderByRaw("CASE WHEN function_type = 'core' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get(['id', 'ipcr_submission_id', 'function_type', 'title']);

        $group = 0;
        $sort = 0;
        $prev = null;
        foreach ($rows as $row) {
            $sameParent = $prev && (int) $prev->ipcr_submission_id === (int) $row->ipcr_submission_id;
            if (! $sameParent) {
                $group = 0;
                $sort = 0;
            } else {
                $sameFn = $prev->function_type === $row->function_type
                    && (string) ($prev->title ?? '') === (string) ($row->title ?? '');
                if (! $sameFn) {
                    $group++;
                }
                $sort++;
            }
            DB::table('commitments')->where('id', $row->id)->update([
                'function_group' => $group,
                'sort_order' => $sort,
            ]);
            $prev = $row;
        }
    }
};
