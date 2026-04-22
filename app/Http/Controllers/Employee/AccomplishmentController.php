<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Accomplishment;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccomplishmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'commitment_id' => $request->filled('commitment_id') ? $request->input('commitment_id') : null,
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'commitment_id' => ['nullable', 'exists:commitments,id'],
        ]);

        $user = $request->user();

        if (! empty($data['commitment_id'])) {
            $owns = $user->commitments()->whereKey($data['commitment_id'])->exists();
            abort_unless($owns, 403);
        }

        $accomplishment = Accomplishment::create([
            'user_id' => $user->id,
            'commitment_id' => $data['commitment_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => null,
        ]);

        AuditLogger::log($user->id, 'accomplishment.created', $accomplishment, null, $request);

        return back();
    }
}
