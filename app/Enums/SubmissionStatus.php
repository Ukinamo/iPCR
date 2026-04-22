<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Returned = 'returned';
}
