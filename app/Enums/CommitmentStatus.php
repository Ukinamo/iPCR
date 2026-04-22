<?php

namespace App\Enums;

enum CommitmentStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Returned = 'returned';
}
