<?php

namespace App\Enums;

enum StoMonitoringReportType: string
{
    case Stufap = 'stufap';
    case StudentServices = 'student_services';

    public function defaultTitle(): string
    {
        return match ($this) {
            self::Stufap => 'REPORT ON STO: Monitoring of HEI with STUFAPs',
            self::StudentServices => 'REPORT ON STO: Monitoring of Student Services',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Stufap => 'STUFAP monitoring',
            self::StudentServices => 'Student services monitoring',
        };
    }
}
