<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoredApp extends Model
{
    protected $fillable = ['name', 'url', 'token', 'enabled'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'enabled' => 'boolean',
        ];
    }
}
