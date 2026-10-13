<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffirmationMedia extends Model
{
    protected $table = 'affirmation_media';
    protected $fillable = ['affirmation_id', 'type', 'file_url', 'duration', 'file_size', 'mime_type'];
}
