<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LocalSeniMatch extends Model
{
    use HasFactory;

    protected $table = 'local_seni_matches';

    protected $fillable = [
        'arena_name',
        'tournament_name',
        'start_time',
        'pause_time',
        'end_time',
        'status',
        'duration',
        'match_order',
        'match_type',
        'disqualified',
        'disqualification_reason',
        // tambahkan kolom lain yang boleh diisi massal
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'pause_time' => 'datetime',
        'end_time' => 'datetime',
        'duration' => 'integer',
    ];
}
