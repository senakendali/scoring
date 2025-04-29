<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchPersonnelAssignment extends Model
{
    use HasFactory;

    protected $table = 'match_personnel_assignments';

    protected $fillable = [
        'tournament_name',
        'arena_name',
        'tipe_pertandingan',
        'role',
        'juri_number',
    ];
    
}
