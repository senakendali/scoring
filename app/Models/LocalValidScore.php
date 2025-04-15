<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalValidScore extends Model
{
    protected $table = 'local_valid_scores';

    protected $fillable = [
        'local_match_id',
        'round_id',
        'corner',
        'type',
        'point',
        'validated_at',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    public function match()
    {
        return $this->belongsTo(LocalMatch::class, 'match_id');
    }

    public function round()
    {
        return $this->belongsTo(LocalMatchRound::class, 'round_id');
    }
}
