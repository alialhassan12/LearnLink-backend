<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable('user_id','conversation_id')]
class ConversationParticipant extends Model
{
    //relationships
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function conversation(){
        return $this->belongsTo(Conversation::class);
    }
}
