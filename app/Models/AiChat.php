<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable('user_id','title')]
class AiChat extends Model
{
    //relationship
    public function user(){
        return $this->belongsTo(User::class);
    }

    public function aiMessages(){
        return $this->hasMany(AiMessage::class)->orderBy('created_at','asc');
    }
}
