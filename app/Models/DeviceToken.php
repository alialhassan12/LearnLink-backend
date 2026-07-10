<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(["user_id","push_token","platform"])]
class DeviceToken extends Model
{
    //relationships
    public function user(){
        return $this->belongsTo(User::class);
    }
}
