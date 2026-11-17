<?php

namespace App\Models;

use App\Models\Traits\Translate;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['id', 'created_at', 'updated_at'])]
class Layout extends Model
{
    use Translate;
}
