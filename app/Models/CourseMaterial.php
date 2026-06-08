<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['section_id','title','path','type','size']) ]
class CourseMaterial extends Model
{
    //Relationship
    public function section():BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }
}
