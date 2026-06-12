<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use App\Services\LiveKitService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class liveSessionsController extends Controller
{
    public function getToken(Request $request, LiveKitService $liveKit){
        $request->validate([
            "room_name"=>"required|string",            
        ]);

        $user=$request->user();

        $token=$liveKit->generateToken(
            $request->room_name,
            $user->name
        );

        return response()->json([
            "url"=>config('livekit.url'),
            "token"=>$token
        ],200);
    }

    public function endSession(Request $request){
        $request->validate([
            "session_id"=>"required|integer",
        ]);

        $user=$request->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthorized"
            ],401);
        }

        $teacher=$user->teacher;
        if(!$teacher){
            return response()->json([
                "message"=>"Unautharized Access"
            ],401);
        }

        $session=LiveSession::with('booking')->where("id",$request->session_id)->first();
        if(!$session){
            return response()->json([
                "message"=>"Session not found"
            ],404);
        }

        $booking=$session->booking;
        if($booking->teacher_id !== $teacher->id){
            return response()->json([
                "message"=>"Unauthorized Access"
            ],401);
        }

        $session->status="completed";
        $session->save();

        return response()->json([
            "message"=>"Session ended successfully",
            "session"=>$session
        ],200);
    }

    public function getTeacherLiveSessions(Request $request, SupabaseStorageService $storage){
        $user=$request->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthorized"
            ],401);
        }
        $teacher=$user->teacher;
        if(!$teacher){
            return response()->json([
                "message"=>"Unautharized Access"
            ],401);
        }

        // load subscription and plan to avoid n+1 query issue
        $user->load('subscription.plan');
        $max_live_sessions=$user->subscription->plan->features['sessions_per_month'];

        $bookings=$teacher->approvedBookings()->with('liveSession','student.user')->get();
        $live_sessions=[];
        foreach($bookings as $booking){
            if($booking->student->user->avatar){
                $avatar=$storage->getPublicUrl($booking->student->user->avatar);
                $booking->student->user->avatar=$avatar;
            }
            $session=$booking->liveSession;
            $session->student=$booking->student;
            $live_sessions[]=$session;
        }

        return response()->json([
            "message"=>"Live sessions fetched successfully",
            "live_sessions"=>$live_sessions,
            "max_live_sessions"=>$max_live_sessions
        ],200);
    }

    public function getStudentLiveSessions(Request $request, SupabaseStorageService $storage){
        $user=$request->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthorized"
            ],401);
        }
        $student=$user->student;
        if(!$student){
            return response()->json([
                "message"=>"Unauthorized"
            ],401);
        }

        $bookings=$student->approvedBookings()->with('liveSession','teacher.user')->get();
        $live_sessions=[];
        foreach($bookings as $booking){
            if($booking->teacher->user->avatar){
                $avatar=$storage->getPublicUrl($booking->teacher->user->avatar);
                $booking->teacher->user->avatar=$avatar;
            }
            $session=$booking->liveSession;
            $session->teacher=$booking->teacher;
            $live_sessions[]=$session;
        }

        return response()->json([
            "message"=>"Live sessions fetched successfully",
            "live_sessions"=>$live_sessions
        ],200);
    }

    public function getTeacherSessionById(Request $request,SupabaseStorageService $storage,int $id){

        $user=$request->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated"
            ],401);
        }

        $teacher=$user->teacher;
        if(!$teacher){
            return response()->json([
                "message"=>"Unautharized Access"
            ],401);
        }

        $session=LiveSession::where("id",$id)->with('sessionMaterials','student.user')->first();
        if(!$session){
            return response()->json([
                "message"=>"Session not found"
            ],404);
        }

        if($session->student->user->avatar){
            $session->student->user->avatar=$storage->getPublicUrl($session->student->user->avatar);
        }
        if($session->sessionMaterials->count()>0){
            foreach($session->sessionMaterials as $material){
                $material->file_url=$storage->getTemporaryUrl($material->file_url);
            }
        }

        return response()->json([
            "message"=>"Session fetched successfully",
            "session"=>$session
        ],200);
    }

    public function getStudentSessionById(Request $request,SupabaseStorageService $storage,int $id){
        $user=$request->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthorized"
            ],401);
        }
        $student=$user->student;
        if(!$student){
            return response()->json([
                "message"=>"Unauthorized"
            ],401);
        }
        
        $session=LiveSession::where("id",$id)->with("sessionMaterials","teacher.user")->first();
        if(!$session){
            return response()->json([
                "message"=>"Session not found"
            ],404);
        }

        if($session->teacher->user->avatar){
            $session->teacher->user->avatar=$storage->getPublicUrl($session->teacher->user->avatar);
        }

        if($session->sessionMaterials->count()>0){
            foreach($session->sessionMaterials as $material){
                $material->file_url=$storage->getTemporaryUrl($material->file_url);
            }
        }

        return response()->json([
            "message"=>"Session fetched successfully",
            "session"=>$session
        ],200);
    }
}
