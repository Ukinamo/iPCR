<?php

namespace App\Enums;

enum RegisterReportStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Returned = 'returned';

    public function supervisorCanEdit(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }
}
