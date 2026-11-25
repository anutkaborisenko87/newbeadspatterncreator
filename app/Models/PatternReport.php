<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatternReport extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['drawing' => 'array'];

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
