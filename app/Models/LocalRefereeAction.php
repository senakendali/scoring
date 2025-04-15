<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalRefereeAction extends Model
{
    protected $table = 'local_referee_actions';

    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function localMatch()
    {
        return $this->belongsTo(LocalMatch::class);
    }
}
