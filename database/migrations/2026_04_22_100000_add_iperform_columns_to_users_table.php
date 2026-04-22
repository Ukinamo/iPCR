<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('employee')->after('password');
            $table->string('account_status', 32)->default('active')->after('role');
            $table->foreignId('supervisor_id')->nullable()->after('account_status')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
            $table->dropColumn(['role', 'account_status']);
        });
    }
};
