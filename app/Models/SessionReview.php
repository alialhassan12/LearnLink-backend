<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['live_session_id','rating','review'])]
class SessionReview extends Model
{
    //relationships
    public function liveSession(){
        return $this->belongsTo(LiveSession::class);
    }
}
