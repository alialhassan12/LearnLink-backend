<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable('type','group_name','group_description','last_message_id')]
class Conversation extends Model
{
    //relationships

    public function participants(){
        return $this->hasMany(ConversationParticipant::class);
    }
    public function lastMessage(){
        return $this->belongsTo(Message::class,'last_message_id');
    }
    public function messages(){
        return $this->hasMany(Message::class);
    }
}
