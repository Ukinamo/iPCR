<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_form_template_items', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });

        Schema::table('commitments', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_form_template_items', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });

        Schema::table('commitments', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};
