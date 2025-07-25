<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class AiModel extends Model
{
    protected $fillable = [
        'name',
        'title',
        'is_active',
    ];
}
