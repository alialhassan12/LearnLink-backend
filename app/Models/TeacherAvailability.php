<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['teacher_id','day_of_week','start_time','end_time'])]
class TeacherAvailability extends Model
{
    // Relationship
    public function teacher(){
        return $this->belongsTo(Teacher::class);
    }
    
    // cast
    protected function casts(): array{
        return[
            'start_time'=>'datetime:H:i',
            'end_time'=>'datetime:H:i',
        ];
    }
}
