<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;

#[Fillable(['user_id','title','body','type','data','is_read'])]
class Notification extends Model
{
    //relationships
    public function user(){
        return $this->belongsTo(User::class);
    }

    //casts
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
