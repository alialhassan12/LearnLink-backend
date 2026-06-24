<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CourseEnrollment;
use App\Models\Student;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use stdClass;

class studentController extends Controller
{
    public function getStudent(SupabaseStorageService $storage){
        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "message"=>'Unauthenticated'
            ],401);
        }
        $student=Student::with(['user'])
        ->withCount([
            'bookings as completedSessionsCount' => function($query) {
                $query->where('status', 'approved')
                    ->whereHas('liveSession', function($q) {
                        $q->where('status', 'completed');
                    });
            },
            'bookings as upcommingSessionsCount' => function($query) {
                $query->where('status', 'approved')
                    ->whereHas('liveSession', function($q) {
                        $q->where('status', 'booked');
                    });
            },
            'enrollments as enrolledCoursesCount'
        ])
        ->where('user_id', $user->id)->first();

        if(!$student){
            return response()->json([
                "message"=>'Unauthorized'
            ],403);
        }

        $upcommingSessions=Booking::with('liveSession.teacher.user')->where('student_id', $student->id)->where('status', 'approved')->whereHas('liveSession', function($query) {
            $query->where('status','booked');
        })->limit(2)->get();

        $enrolledCourses=CourseEnrollment::with('course.teacher.user','course.category')->where('student_id', $student->id)->limit(2)->get();

        return response()->json([
            "message"=>"Student details fetched successfully",
            "student"=>$student,
            "completed_sessions_count"=>$student->completedSessionsCount,
            "upcomming_sessions_count"=>$student->upcommingSessionsCount,
            "enrolled_courses_count"=>$student->enrolledCoursesCount,
            "upcomming_sessions"=>$upcommingSessions,
            "enrolled_courses"=>$enrolledCourses
        ]);
    }

    public function getLoggedInStudentId(){
        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated"
            ],401);
        }
        $student=Student::select('id')->where('user_id',$user->id)->first();
        if(!$student){
            return response()->json([
                "message"=>"Unauthorized"
            ],403);
        }
        return response()->json([
            "message"=>"Student ID fetched successfully",
            "student_id"=>$student->id
        ]);
    }

    public function editStudentProfile(Request $request,SupabaseStorageService $storage){
        $request->validate([
            "name"=>"string",
            "headline"=>"string",
            "avatar"=>"file|max:2048|mimes:jpg,jpeg,png,webp"
        ]);

        $user=$request->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated"
            ],401);
        }
        $student=Student::where('user_id',$user->id)->first();
        if(!$student){
            return response()->json([
                "message"=>"Unauthorized"
            ],403);
        }

        if($request->hasFile('avatar')){
            $avatar=$request->file('avatar');
            $avatarPath=$storage->uploadAvatar($avatar,$user->id,$user->avatar);
            $user->update([
                'name'=>$request->name,
                'avatar'=>$avatarPath,
            ]);
        }else{
            $user->update([
                'name'=>$request->name,
            ]);
        }

        $studentData=[
            'headline'=>$request->headline,
            'bio'=>$request->bio
        ];
        
        $student->update($studentData);
        
        $user->save();
        $student->save();
        
        $student->load('user');

        return response()->json([
            "message"=>"Profile updated successfully",
            "student"=>$student,
        ],200);
    }
}
