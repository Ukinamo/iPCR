<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

class Accomplishment extends Model
{
    protected $fillable = [
        'user_id',
        'commitment_id',
        'title',
        'description',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
    ];

    protected $appends = [
        'file_url',
        'download_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commitment(): BelongsTo
    {
        return $this->belongsTo(Commitment::class);
    }

    public function getFileUrlAttribute(): ?string
    {
        if ($this->file_path === null || $this->file_path === '') {
            return null;
        }

        if (! app()->runningInConsole() && Route::has('accomplishments.file')) {
            return route('accomplishments.file', $this->id);
        }

        return Storage::disk('public')->url($this->file_path);
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if ($this->file_url === null) {
            return null;
        }

        return $this->file_url.(str_contains($this->file_url, '?') ? '&' : '?').'download=1';
    }
}
