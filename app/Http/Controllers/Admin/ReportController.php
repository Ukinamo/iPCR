<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function usersCsv(): StreamedResponse
    {
        $filename = 'iperform-users-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'email', 'role', 'account_status', 'supervisor_email']);

            User::query()
                ->with('supervisor:id,email')
                ->orderBy('name')
                ->chunk(200, function ($users) use ($out) {
                    foreach ($users as $u) {
                        fputcsv($out, [
                            $u->name,
                            $u->email,
                            $u->role->value,
                            $u->account_status->value,
                            $u->supervisor?->email,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
