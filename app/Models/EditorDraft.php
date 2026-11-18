<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Guarded([])]
#[Table(key: 'user_id', incrementing: false, timestamps: false)]
class EditorDraft extends Model
{
    protected $casts = ['version' => 'integer', 'drawing' => 'array'];

    public function snapshot(): array
    {
        return ['version' => $this->version, 'drawing' => $this->drawing];
    }
}
