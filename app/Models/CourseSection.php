<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(["course_id","title","order"])]
class CourseSection extends Model
{
    //Relationship
    public function course(){
        return $this->belongsTo(Course::class);
    }

    public function materials(){
        return $this->hasMany(CourseMaterial::class, 'section_id');
    }
}
