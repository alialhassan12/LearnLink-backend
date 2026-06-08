<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable("title","status")]
class Category extends Model
{
    // Relations
    public function courses(){
        return $this->hasMany(Course::class);
    }
}
