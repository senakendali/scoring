<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalSeniScore extends Model
{
    protected $fillable = [
        'local_match_id',
        'judge_number',
        'deduction',
        'deducted_at',
    ];

    protected $casts = [
        'deducted_at' => 'datetime',
    ];

    public function match()
    {
        return $this->belongsTo(LocalSeniMatch::class, 'local_match_id');
    }
}
