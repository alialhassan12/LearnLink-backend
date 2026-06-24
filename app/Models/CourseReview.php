<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['course_id','student_id','rating','review'])]
class CourseReview extends Model
{
    //relationships
    public function course(){
        return $this->belongsTo(Course::class);
    }
    public function student(){
        return $this->belongsTo(Student::class);
    }
}
