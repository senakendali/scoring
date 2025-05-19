<?php

namespace App\Models;

use App\Models\LocalSeniMatch;
use Illuminate\Database\Eloquent\Model;

class LocalSeniFinalScore extends Model
{
    protected $fillable = [
        'local_match_id',
        'judge_number',
        'kemantapan',
        'ekspresi',
        'kekompakan',
        'submitted_at',
    ];
    
    public function match()
    {
        return $this->belongsTo(LocalSeniMatch::class, 'local_match_id');
    }

}
