<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectVersion extends Model
{
    public const CLIENT_DESKTOP = 'desktop';

    public const CLIENT_GAME = 'game';

    protected $fillable = [
        'client_type',
        'versao',
        'notas',
    ];
}
