<?php

namespace App\Enums;

enum UserRole: string
{
    case Employee = 'employee';
    case Supervisor = 'supervisor';
    case Administrator = 'administrator';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Employee',
            self::Supervisor => 'Supervisor / Rater',
            self::Administrator => 'Administrator',
        };
    }
}
