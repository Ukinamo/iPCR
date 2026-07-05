<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\IpcrSubmission;
use App\Models\SubmissionReviewTransferRequest;
use App\Services\SubmissionReviewTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubmissionReviewTransferController extends Controller
{
    public function store(Request $request, IpcrSubmission $submission, SubmissionReviewTransferService $service): RedirectResponse
    {
        $data = $request->validate([
            'to_supervisor_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->createRequest($request->user(), $submission, $data);

        return back()->with('status', 'Review transfer sent. The receiving supervisor must accept before they can review this package.');
    }

    public function destroy(Request $request, SubmissionReviewTransferRequest $reviewTransfer, SubmissionReviewTransferService $service): RedirectResponse
    {
        $service->cancelRequest($request->user(), $reviewTransfer);

        return back()->with('status', 'Review transfer cancelled.');
    }

    public function accept(Request $request, SubmissionReviewTransferRequest $reviewTransfer, SubmissionReviewTransferService $service): RedirectResponse
    {
        $data = $request->validate([
            'response_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->accept($request->user(), $reviewTransfer, $data['response_notes'] ?? null);

        return back()->with('status', 'Review transfer accepted. This package is now assigned to you for review.');
    }

    public function reject(Request $request, SubmissionReviewTransferRequest $reviewTransfer, SubmissionReviewTransferService $service): RedirectResponse
    {
        $data = $request->validate([
            'response_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->reject($request->user(), $reviewTransfer, $data['response_notes'] ?? null);

        return back()->with('status', 'Review transfer declined.');
    }
}
