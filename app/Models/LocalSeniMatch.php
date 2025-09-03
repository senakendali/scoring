<?php

namespace App\Models;

use App\Models\LocalSeniFinalScore;
use App\Models\LocalSeniComponentScore;

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
        'winner_corner',
        // tambahkan kolom lain yang boleh diisi massal
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'pause_time' => 'datetime',
        'end_time' => 'datetime',
        'duration' => 'integer',
    ];

    public function finalScores()
    {
        return $this->hasMany(LocalSeniFinalScore::class, 'local_match_id');
    }

    public function componentScores()
    {
        return $this->hasMany(LocalSeniComponentScore::class, 'local_match_id');
    }


}
