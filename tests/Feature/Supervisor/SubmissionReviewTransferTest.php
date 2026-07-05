<?php

namespace Tests\Feature\Supervisor;

use App\Enums\ReviewTransferRequestStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\IpcrSubmission;
use App\Models\SubmissionReviewTransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionReviewTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_request_review_transfer_without_reassigning_employee(): void
    {
        $supervisorA = User::factory()->create(['role' => UserRole::Supervisor]);
        $supervisorB = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisorA->id,
        ]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisorA->id,
            'evaluation_year' => 2026,
            'evaluation_quarter' => 2,
            'status' => SubmissionStatus::InReview,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($supervisorA)->post(
            route('supervisor.submissions.review-transfers.store', $submission),
            [
                'to_supervisor_id' => $supervisorB->id,
                'reason' => 'Coverage while I am on leave.',
            ],
        );

        $response->assertRedirect();
        $employee->refresh();
        $submission->refresh();

        $this->assertSame($supervisorA->id, $employee->supervisor_id);
        $this->assertSame($supervisorA->id, $submission->supervisor_id);
        $this->assertDatabaseHas('submission_review_transfer_requests', [
            'ipcr_submission_id' => $submission->id,
            'requested_by_id' => $supervisorA->id,
            'to_supervisor_id' => $supervisorB->id,
            'status' => ReviewTransferRequestStatus::Pending->value,
        ]);
    }

    public function test_receiving_supervisor_accept_moves_submission_only(): void
    {
        $supervisorA = User::factory()->create(['role' => UserRole::Supervisor]);
        $supervisorB = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisorA->id,
        ]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisorA->id,
            'evaluation_year' => 2026,
            'evaluation_quarter' => 2,
            'status' => SubmissionStatus::InReview,
            'submitted_at' => now(),
        ]);

        $transfer = SubmissionReviewTransferRequest::create([
            'ipcr_submission_id' => $submission->id,
            'requested_by_id' => $supervisorA->id,
            'from_supervisor_id' => $supervisorA->id,
            'to_supervisor_id' => $supervisorB->id,
            'status' => ReviewTransferRequestStatus::Pending,
        ]);

        $response = $this->actingAs($supervisorB)->patch(
            route('supervisor.review-transfers.accept', $transfer),
            ['response_notes' => 'Happy to review.'],
        );

        $response->assertRedirect();
        $employee->refresh();
        $submission->refresh();
        $transfer->refresh();

        $this->assertSame($supervisorA->id, $employee->supervisor_id);
        $this->assertSame($supervisorB->id, $submission->supervisor_id);
        $this->assertSame(ReviewTransferRequestStatus::Accepted, $transfer->status);
    }

    public function test_receiving_supervisor_can_reject_without_changing_submission(): void
    {
        $supervisorA = User::factory()->create(['role' => UserRole::Supervisor]);
        $supervisorB = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisorA->id,
        ]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisorA->id,
            'evaluation_year' => 2026,
            'evaluation_quarter' => 2,
            'status' => SubmissionStatus::Returned,
            'submitted_at' => now(),
        ]);

        $transfer = SubmissionReviewTransferRequest::create([
            'ipcr_submission_id' => $submission->id,
            'requested_by_id' => $supervisorA->id,
            'from_supervisor_id' => $supervisorA->id,
            'to_supervisor_id' => $supervisorB->id,
            'status' => ReviewTransferRequestStatus::Pending,
        ]);

        $response = $this->actingAs($supervisorB)->patch(
            route('supervisor.review-transfers.reject', $transfer),
            ['response_notes' => 'Not available this quarter.'],
        );

        $response->assertRedirect();
        $submission->refresh();
        $transfer->refresh();

        $this->assertSame($supervisorA->id, $submission->supervisor_id);
        $this->assertSame(ReviewTransferRequestStatus::Rejected, $transfer->status);
    }

    public function test_requesting_supervisor_can_cancel_pending_review_transfer(): void
    {
        $supervisorA = User::factory()->create(['role' => UserRole::Supervisor]);
        $supervisorB = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisorA->id,
        ]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisorA->id,
            'evaluation_year' => 2026,
            'evaluation_quarter' => 2,
            'status' => SubmissionStatus::InReview,
            'submitted_at' => now(),
        ]);

        $transfer = SubmissionReviewTransferRequest::create([
            'ipcr_submission_id' => $submission->id,
            'requested_by_id' => $supervisorA->id,
            'from_supervisor_id' => $supervisorA->id,
            'to_supervisor_id' => $supervisorB->id,
            'status' => ReviewTransferRequestStatus::Pending,
        ]);

        $response = $this->actingAs($supervisorA)->delete(
            route('supervisor.review-transfers.destroy', $transfer),
        );

        $response->assertRedirect();
        $transfer->refresh();

        $this->assertSame(ReviewTransferRequestStatus::Cancelled, $transfer->status);
    }
}
