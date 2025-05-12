<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalSeniPenalties extends Model
{
    protected $fillable = [
        'local_match_id',
        'reason',
        'penalty_value',
    ];

    public function match()
    {
        return $this->belongsTo(LocalSeniMatch::class, 'local_match_id');
    }
}
