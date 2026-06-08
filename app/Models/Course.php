<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(["teacher_id","category_id","title","description","thumbnail","language","status","price"])]

class Course extends Model
{
    // Relations
    public function teacher(){
        return $this->belongsTo(Teacher::class);
    }

    public function category(){
        return $this->belongsTo(Category::class);
    }

    public function sections(){
        return $this->hasMany(CourseSection::class)->orderBy('order','asc');
    }

    public function enrollments(){
        return $this->hasMany(CourseEnrollment::class);
    }
}
