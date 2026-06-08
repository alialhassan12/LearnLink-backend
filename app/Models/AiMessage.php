<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable('ai_chat_id', 'role', 'content','file_name','file_path','file_type', 'type', 'tokens_used')]
class AiMessage extends Model
{

    //relatuionships

    public function aiChat(){
        return $this->belongsTo(AiChat::class);
    }
}
