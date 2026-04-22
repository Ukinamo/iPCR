<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Accomplishment extends Model
{
    protected $fillable = [
        'user_id',
        'commitment_id',
        'title',
        'description',
        'file_path',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commitment(): BelongsTo
    {
        return $this->belongsTo(Commitment::class);
    }
}
