<?php

namespace App\Http\Controllers\Employee;

use App\Enums\CommitmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Accomplishment;
use App\Models\Commitment;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccomplishmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'commitment_id' => $request->filled('commitment_id') ? $request->input('commitment_id') : null,
        ]);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:8000'],
            'commitment_id' => ['required', 'exists:commitments,id'],
            'files' => ['nullable', 'array', 'max:20'],
            'files.*' => ['file', 'max:12288', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip'],
            'file' => ['nullable', 'file', 'max:12288', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip'],
        ]);

        $user = $request->user();

        $commitment = Commitment::query()->whereKey($data['commitment_id'])->firstOrFail();

        abort_unless($commitment->user_id === $user->id, 403);

        if (! in_array($commitment->status, [CommitmentStatus::Draft, CommitmentStatus::Returned], true)) {
            throw ValidationException::withMessages([
                'commitment_id' => 'You can only add evidence to commitments that are in draft or returned for revision.',
            ]);
        }

        /** @var list<\Illuminate\Http\UploadedFile> $files */
        $files = $request->file('files') ?? [];
        if (! is_array($files)) {
            $files = [$files];
        }
        if ($request->hasFile('file')) {
            $files[] = $request->file('file');
        }

        $sharedTitle = trim((string) ($data['title'] ?? ''));
        $sharedDescription = $data['description'] ?? null;

        if (empty($files) && $sharedTitle === '' && $sharedDescription === null) {
            throw ValidationException::withMessages([
                'files' => 'Attach at least one file, or provide a subject.',
            ]);
        }

        if (empty($files)) {
            if ($sharedTitle === '') {
                throw ValidationException::withMessages([
                    'title' => 'Please provide a subject when saving without a file.',
                ]);
            }

            $accomplishment = Accomplishment::create([
                'user_id' => $user->id,
                'commitment_id' => $data['commitment_id'],
                'title' => $sharedTitle,
                'description' => $sharedDescription,
            ]);
            AuditLogger::log($user->id, 'accomplishment.created', $accomplishment, null, $request);

            return back()->with('status', 'Evidence saved.');
        }

        foreach ($files as $file) {
            $path = $file->store('commitment-evidence/'.$user->id, 'public');
            $original = $file->getClientOriginalName();
            $mime = $file->getClientMimeType() ?: $file->getMimeType();
            $size = $file->getSize();

            $title = $sharedTitle !== '' ? $sharedTitle : $original;

            $accomplishment = Accomplishment::create([
                'user_id' => $user->id,
                'commitment_id' => $data['commitment_id'],
                'title' => $title,
                'description' => $sharedDescription,
                'file_path' => $path,
                'original_filename' => $original,
                'mime_type' => $mime,
                'file_size' => $size,
            ]);

            AuditLogger::log($user->id, 'accomplishment.created', $accomplishment, null, $request);
        }

        $count = count($files);

        return back()->with('status', $count === 1 ? 'Evidence saved.' : "Saved {$count} evidence files.");
    }

    public function destroy(Request $request, Accomplishment $accomplishment): RedirectResponse
    {
        $user = $request->user();

        abort_unless($accomplishment->user_id === $user->id, 403);

        $commitment = $accomplishment->commitment;
        if ($commitment !== null) {
            abort_unless(
                in_array($commitment->status, [CommitmentStatus::Draft, CommitmentStatus::Returned], true),
                422,
            );
        }

        $deletedId = $accomplishment->id;

        if ($accomplishment->file_path) {
            Storage::disk('public')->delete($accomplishment->file_path);
        }

        $accomplishment->delete();

        AuditLogger::log($user->id, 'accomplishment.deleted', null, ['id' => $deletedId], $request);

        return back()->with('status', 'Evidence removed.');
    }
}
