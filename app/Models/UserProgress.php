<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProgress extends Model
{
    protected $table = 'user_progress';
    protected $fillable = ['user_id', 'affirmation_id', 'content_type', 'progress', 'completed_at'];

    protected function casts(): array { return ['completed_at' => 'datetime']; }
}
