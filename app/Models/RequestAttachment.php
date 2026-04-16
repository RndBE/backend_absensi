<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RequestAttachment extends Model
{
    protected $fillable = ['attachable_type', 'attachable_id', 'file_path', 'file_name', 'file_size'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
