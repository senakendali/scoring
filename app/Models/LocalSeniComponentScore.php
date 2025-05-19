<?php

namespace App\Models;

use App\Models\LocalSeniMatch;

use Illuminate\Database\Eloquent\Model;

class LocalSeniComponentScore extends Model
{
     protected $fillable = [
        'local_match_id',
        'judge_number',
        'attack_defense_technique',
        'firmness_harmony',
        'soulfulness',
        'submitted_at',
    ];
    public function match()
    {
        return $this->belongsTo(LocalSeniMatch::class, 'local_match_id');
    }
}
