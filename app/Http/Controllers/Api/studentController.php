<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;

class studentController extends Controller
{
    public function getStudent(SupabaseStorageService $storage){
        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "message"=>'Unauthenticated'
            ],401);
        }
        $student=$user->student;
        if(!$student){
            return response()->json([
                "message"=>'Unauthorized'
            ],403);
        }

        $completedSessionsCount = Booking::where('student_id', $student->id)
            ->where('status', 'approved')
            ->whereHas('liveSession', function($query) {
                $query->where('status', 'completed');
            })
            ->count();

        $student->load('user');

        if($student->user && $student->user->avatar){
            $student->user->avatar = $storage->getPublicUrl($student->user->avatar);
        }

        return response()->json([
            "message"=>"Student details fetched successfully",
            "student"=>$student,
            "completed_sessions"=>$completedSessionsCount
        ]);
    }
}
