<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalMatchRound extends Model
{
    protected $table = 'local_match_rounds';

    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function localMatch()
    {
        return $this->belongsTo(LocalMatch::class);
    }

    public function judgeScores()
    {
        return $this->hasMany(LocalJudgeScore::class);
    }

}
