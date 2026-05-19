<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectVersion extends Model
{
    public const CLIENT_DESKTOP = 'desktop';

    protected $fillable = [
        'client_type',
        'versao',
        'notas',
    ];
}
