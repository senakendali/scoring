<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalJudgeScore extends Model
{
    protected $table = 'local_judge_scores';

    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function localMatchRound()
    {
        return $this->belongsTo(LocalMatchRound::class);
    }
}
