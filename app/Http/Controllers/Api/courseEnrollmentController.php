<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class courseEnrollmentController extends Controller
{
    public function createEnrollment(Request $request){
        $request->validate([
            "course_id"=>"required|exists:courses,id",
        ]);

        $user=$request->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated",
                
            ],401);
        }
        $student=$user->student;
        if(!$student){
            return response()->json([
                "message"=>"Unautharized Access"
            ],403);
        }

        $enrollment=DB::transaction(function() use ($request,$student){
            $existingEnrollment=CourseEnrollment::where('student_id',$student->id)
                                                ->where('course_id',$request->course_id)->first();
            
            if($existingEnrollment){
                return response()->json([
                    "message"=>"You are already enrolled in this course"
                ],409);
            }

            $enrollment=CourseEnrollment::create([
                "student_id"=>$student->id,
                "course_id"=>$request->course_id,
            ]);

            return $enrollment;
        });

        return response()->json([
            "message"=>"You are successfully enrolled in this course",
            "enrollment"=>$enrollment
        ],201);
    }

    public function getEnrolledCoursesIds(Request $request){
        $user=$request->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated",
                
            ],401);
        }
        $student=$user->student;
        if(!$student){
            return response()->json([
                "message"=>"Unautharized Access"
            ],403);
        }

        $enrolledCourses=CourseEnrollment::where('student_id',$student->id)->pluck('course_id')->toArray();

        return response()->json([
            "enrolled_courses_ids"=>$enrolledCourses
        ],200);
    }

    public function getEnrolledCourses(Request $request,SupabaseStorageService $storage){
        $user=$request->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated",
                
            ],401);
        }
        $student=$user->student;
        if(!$student){
            return response()->json([
                "message"=>"Unautharized Access"
            ],403);
        }

        $enrollments=CourseEnrollment::where('student_id',$student->id)->with('course.teacher.user','course.category')->get();
        
        foreach($enrollments as $enrollment){
            if($enrollment->course->thumbnail){
                $enrollment->course->thumbnail=$storage->getPublicUrl($enrollment->course->thumbnail);
            }
            if($enrollment->course->teacher->user->avatar){
                $enrollment->course->teacher->user->avatar=$storage->getPublicUrl($enrollment->course->teacher->user->avatar);
            }
        }

        return response()->json([
            "message"=>"Enrolled courses retrieved successfully",
            "enrollments"=>$enrollments
        ],200);
    }
}
