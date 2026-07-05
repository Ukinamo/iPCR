<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_review_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_submission_id')->constrained('ipcr_submissions')->cascadeOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->text('response_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'to_supervisor_id'], 'sub_rev_xfer_status_to_sup_idx');
            $table->index(['status', 'requested_by_id'], 'sub_rev_xfer_status_req_by_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_review_transfer_requests');
    }
};
