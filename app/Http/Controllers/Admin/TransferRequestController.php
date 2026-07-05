<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransferRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\SupervisorTransferRequest;
use App\Services\SupervisorTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferRequestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/TransferRequests/Index', [
            'pendingRequests' => SupervisorTransferRequest::query()
                ->with(['employee:id,name,email', 'requestedBy:id,name,email', 'fromSupervisor:id,name', 'toSupervisor:id,name'])
                ->where('status', TransferRequestStatus::Pending)
                ->orderBy('created_at')
                ->get(),
            'recentRequests' => SupervisorTransferRequest::query()
                ->with(['employee:id,name,email', 'requestedBy:id,name,email', 'fromSupervisor:id,name', 'toSupervisor:id,name', 'reviewedBy:id,name'])
                ->whereIn('status', [TransferRequestStatus::Approved, TransferRequestStatus::Rejected, TransferRequestStatus::Cancelled])
                ->orderByDesc('reviewed_at')
                ->orderByDesc('updated_at')
                ->take(20)
                ->get(),
        ]);
    }

    public function approve(Request $request, SupervisorTransferRequest $transferRequest, SupervisorTransferService $service): RedirectResponse
    {
        $data = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->approve($request->user(), $transferRequest, $data['admin_notes'] ?? null);

        return back()->with('status', 'Supervisor transfer approved. The employee and open IPCR packages were reassigned.');
    }

    public function reject(Request $request, SupervisorTransferRequest $transferRequest, SupervisorTransferService $service): RedirectResponse
    {
        $data = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->reject($request->user(), $transferRequest, $data['admin_notes'] ?? null);

        return back()->with('status', 'Supervisor transfer rejected.');
    }
}
