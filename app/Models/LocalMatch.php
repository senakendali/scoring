<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalMatch extends Model
{
    protected $table = 'local_matches';

    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function rounds()
    {
        return $this->hasMany(LocalMatchRound::class);
    }

    public function refereeActions()
    {
        return $this->hasMany(LocalRefereeAction::class);
    }

    public function logs()
    {
        return $this->hasMany(LocalMatchLog::class);
    }

}
