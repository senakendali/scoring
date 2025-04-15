<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalMatchLog extends Model
{
    protected $table = 'local_match_logs';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function localMatch()
    {
        return $this->belongsTo(LocalMatch::class);
    }   

    
}
