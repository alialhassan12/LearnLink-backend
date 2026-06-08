<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

#[Fillable(['user_id','headline','location','bio','subjects','languages','hourly_rate','sessions_created'])]
class Teacher extends Model
{
    // Relations
    public function user(){
        return $this->belongsTo(User::class);
    }

    // courses for public view
    public function publishedCourses(){
        return $this->hasMany(Course::class)->where('status','published');
    }

    // all courses for teacher
    public function courses(){
        return $this->hasMany(Course::class);
    }

    public function availabilities(){
        return $this->hasMany(TeacherAvailability::class);
    }

    public function bookings(){
        return $this->hasMany(Booking::class);
    }

    public function approvedBookings(){
        return $this->hasMany(Booking::class)->where('status','approved');
    }

    // Casts
    protected function casts(): array{
        return [
            'subjects' => 'array',
            'languages' => 'array',
            'hourly_rate' => 'decimal:2'
        ];
    }
}
