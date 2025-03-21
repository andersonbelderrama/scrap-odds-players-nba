<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NBAPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'team_name',
        'is_starter',
        'jersey_number',
    ];

    protected $casts = [
        'is_starter' => 'boolean',
    ];

    public function stats()
    {
        return $this->hasMany(NBAPlayerStat::class);
    }
}
