<?php

namespace App\Models\Concerns;

use App\Enums\RegisterReportStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasRegisterReportReview
{
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function supervisorCanEdit(): bool
    {
        $status = $this->status instanceof RegisterReportStatus
            ? $this->status
            : RegisterReportStatus::tryFrom((string) $this->status);

        return ($status ?? RegisterReportStatus::Draft)->supervisorCanEdit();
    }

    public function statusValue(): string
    {
        if ($this->status instanceof RegisterReportStatus) {
            return $this->status->value;
        }

        return (string) ($this->status ?: RegisterReportStatus::Draft->value);
    }
}
