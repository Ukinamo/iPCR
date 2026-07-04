<?php

namespace App\Services;

use App\Enums\CommitmentStatus;
use App\Enums\SubmissionStatus;
use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CommitmentPeriodGuard
{
    public static function addCommitmentBlockedReason(User|int $user, int $year, int $quarter): ?string
    {
        $userId = $user instanceof User ? $user->id : $user;

        $submission = IpcrSubmission::query()
            ->where('employee_id', $userId)
            ->where('evaluation_year', $year)
            ->where('evaluation_quarter', $quarter)
            ->first();

        if ($submission?->status === SubmissionStatus::Approved) {
            return 'This quarter is already approved. You cannot add new commitments for this period.';
        }

        if ($submission?->status === SubmissionStatus::InReview) {
            return 'Your IPCR package is with your supervisor for review. Wait until it is returned or approved before adding commitments.';
        }

        $hasPendingReview = Commitment::query()
            ->where('user_id', $userId)
            ->where('evaluation_year', $year)
            ->where('evaluation_quarter', $quarter)
            ->where('status', CommitmentStatus::InReview)
            ->exists();

        if ($hasPendingReview) {
            return 'You have commitments pending supervisor review. You cannot add new ones until the package is returned or approved.';
        }

        return null;
    }

    public static function assertCanAddCommitments(User $user, int $year, int $quarter, string $errorKey = 'entries'): void
    {
        $reason = self::addCommitmentBlockedReason($user, $year, $quarter);

        if ($reason !== null) {
            throw ValidationException::withMessages([$errorKey => $reason]);
        }
    }
}
