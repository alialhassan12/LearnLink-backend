<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class courseEnrollmentController extends Controller
{
    public function createEnrollment(Request $request,NotificationService $notificationService){
        $request->validate([
            "course_id"=>"required|exists:courses,id",
        ]);

        $user=auth('sanctum')->user();

        $student=$user->student;

        $course=Course::with('teacher.user')->where('status','published')->where('id',$request->course_id)->first();
        if(!$course){
            return response()->json([
                "message"=>"Course not found"
            ],404);
        }

        $existingEnrollment=CourseEnrollment::where('student_id',$student->id)->where('course_id',$request->course_id)->first();
        if($existingEnrollment){
            return response()->json([
                "message"=>"You are already enrolled in this course"
            ],409);
        }
        
        $enrollment=DB::transaction(function() use ($request,$student,$notificationService,$course){
            $enrollment=CourseEnrollment::create([
                "student_id"=>$student->id,
                "course_id"=>$request->course_id,
            ]);

            Notification::create([
                "user_id"=>$course->teacher->user_id,
                "title"=>"New Course Enrollment",
                "body"=>"{$student->user->name} has enrolled in your course {$course->title}",
                "type"=>"course_enrollment",
                "data"=>[
                    "type"=>"course_enrollment",
                    "enrollment_id"=>$enrollment->id
                ]
            ]);

            $notificationService->send(
                $course->teacher->user,
                "New Course Enrollment",
                "{$student->user->name} has enrolled in your course {$course->title}",
                [
                    "type"=>"course_enrollment",
                    "enrollment_id"=>$enrollment->id
                ]
            );


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

        $enrollments=CourseEnrollment::where('student_id',$student->id)
            ->with('course.teacher.user','course.category')
            ->paginate(6);

        return response()->json([
            "message"=>"Enrolled courses retrieved successfully",
            "enrollments"=>$enrollments,
            "pagination"=>[
                "current_page"=>$enrollments->currentPage(),
                "per_page"=>$enrollments->perPage(),
                "total"=>$enrollments->total(),
                "last_page"=>$enrollments->lastPage(),
            ]
        ],200);
    }
}
