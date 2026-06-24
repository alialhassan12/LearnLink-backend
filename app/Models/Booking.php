<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['teacher_id', 'student_id', 'scheduled_day', 'scheduled_date', 'scheduled_time', 'subject', 'student_note', 'status', 'price'])]
class Booking extends Model
{
    //relationships
    public function student(){
        return $this->belongsTo(Student::class);
    }

    public function teacher(){
        return $this->belongsTo(Teacher::class);
    }

    public function liveSession(){
        return $this->hasOne(LiveSession::class);
    }

    public function sessionReview(){
        return $this->hasOne(SessionReview::class);
    }
}
