<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['live_session_id','title','file_url','file_type'])]
class SessionMaterial extends Model
{
    //relationships
    public function liveSession(){
        return $this->belongsTo(LiveSession::class);
    }
}
