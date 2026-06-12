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

        $sessions = Booking::where('student_id', $student->id)
        ->where('status', 'approved')
        ->with('liveSession','teacher.user')->get();

        $completedSessions=$sessions->filter(function($session){
            return $session->liveSession->status==='completed';
        });
        
        

        $student->load('user');

        if($student->user && $student->user->avatar){
            $student->user->avatar = $storage->getPublicUrl($student->user->avatar);
        }
        if($sessions->count()>0){
            $sessions->each(function($session) use($storage){
                if($session->teacher->user && $session->teacher->user->avatar){
                    $session->teacher->user->avatar = $storage->getPublicUrl($session->teacher->user->avatar);
                }
            });
        }

        return response()->json([
            "message"=>"Student details fetched successfully",
            "student"=>$student,
            "completed_sessions"=>$completedSessions->count()
        ]);
    }
}
