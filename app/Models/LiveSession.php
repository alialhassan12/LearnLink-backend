<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['booking_id','scheduled_date','scheduled_day','scheduled_time', 'subject', 'student_note','duration','status','recording_url'])]

class LiveSession extends Model
{
    //relations
    public function booking(){
        return $this->belongsTo(Booking::class);
    }

    public function student(){
        return $this->hasOneThrough(Student::class, Booking::class, 'id', 'id', 'booking_id', 'student_id');
    }

    public function teacher(){
        return $this->hasOneThrough(Teacher::class, Booking::class, 'id', 'id', 'booking_id', 'teacher_id');
    }

    public function sessionMaterials(){
        return $this->hasMany(SessionMaterial::class);
    }

    public function sessionReview(){
        return $this->hasOne(SessionReview::class);
    }

}
