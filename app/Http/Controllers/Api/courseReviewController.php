<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Student;
use Illuminate\Http\Request;

class courseReviewController extends Controller
{
    public function createCourseReview(Request $request){
        $request->validate([
            "course_id"=>"required|exists:courses,id",
            "rating"=>"required|int|min:1|max:5",
            "review_text"=>"nullable|string"
        ]);

        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated"
            ],401);
        }
        
        $student=Student::where('user_id',$user->id)->first();
        if(!$student){
            return response()->json([
                "message"=>"Unauthorized"
            ],401);
        }
        
        $alreadyReviewed=CourseReview::where('student_id',$student->id)->where('course_id',$request->course_id)->exists();
        if($alreadyReviewed){
            return response()->json([
                "message"=>"You have already submitted a review"
            ],400);
        }

        $review=CourseReview::create([
            "course_id"=>$request->course_id,
            "student_id"=>$student->id,
            "rating"=>$request->rating,
            "review"=>$request->review_text
        ]);

        return response()->json([
            "message"=>"review submitted successfully",
            "review"=>$review
        ],201);
    }
}
