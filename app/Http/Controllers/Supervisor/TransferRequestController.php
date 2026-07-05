<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\SupervisorTransferRequest;
use App\Services\SupervisorTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TransferRequestController extends Controller
{
    public function store(Request $request, SupervisorTransferService $service): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'to_supervisor_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->createRequest($request->user(), $data);

        return back()->with('status', 'Transfer request submitted. An administrator must approve it before the employee is reassigned.');
    }

    public function destroy(Request $request, SupervisorTransferRequest $transferRequest, SupervisorTransferService $service): RedirectResponse
    {
        $service->cancelRequest($request->user(), $transferRequest);

        return back()->with('status', 'Transfer request cancelled.');
    }
}
