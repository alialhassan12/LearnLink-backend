<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Teacher;
use Illuminate\Http\Request;

class calendarController extends Controller
{
    public function getTeacherCalendarEvents(Request $request){
        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated!"
            ]);
        }
        
        $teacher=Teacher::with('user')->where('user_id',$user->id)->first();
        if(!$teacher){
            return response()->json([
                "message"=>"Unautharized Access"
            ]);
        }

        //get upcomming sessions
        $upcommingSession=Booking::where('teacher_id',$teacher->id)
                    ->where('status','approved')
                    ->whereDate('scheduled_date','>=',now()->toDateString())
                    ->orderBy('scheduled_date','asc')
                    ->get();

        return response()->json([
            'message'=>'Teacher events fetched successfully',
            'events'=>$upcommingSession
        ]);
    }
}
