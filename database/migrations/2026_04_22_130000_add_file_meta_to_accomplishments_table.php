<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accomplishments', function (Blueprint $table) {
            $table->string('original_filename')->nullable()->after('file_path');
            $table->string('mime_type', 128)->nullable()->after('original_filename');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('accomplishments', function (Blueprint $table) {
            $table->dropColumn(['original_filename', 'mime_type', 'file_size']);
        });
    }
};
