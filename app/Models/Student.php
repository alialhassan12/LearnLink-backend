<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'bio','headline'])]
class Student extends Model
{
    // Relations
    public function user(){
        return $this->belongsTo(User::class);
    }

    public function bookings(){
        return $this->hasMany(Booking::class);
    }

    // get approved bookings
    public function approvedBookings(){
        return $this->hasMany(Booking::class)->where('status','approved');
    }

    public function enrollments(){
        return $this->hasMany(CourseEnrollment::class);
    }
}
