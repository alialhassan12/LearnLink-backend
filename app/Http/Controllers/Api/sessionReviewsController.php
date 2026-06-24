<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\SessionReview;
use App\Models\Student;
use Illuminate\Http\Request;

class sessionReviewsController extends Controller
{
    public function createSessionReview(Request $request){
        $request->validate([
            "live_session_id"=>"required|exists:live_sessions,id",
            "rating"=>"required|integer|min:1|max:5",
            "review_text"=>"nullable|string"
        ]);

        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated"
            ],401);
        }

        $live_session=LiveSession::with('booking','booking.student')->where('id',$request->live_session_id)->first();
        $student=$live_session->booking->student;
        
        if(!$student){
            return response()->json([
                "message"=>"Unauthorized"
            ],403);
        }
        if($user->id !== $student->user_id){
            return response()->json([
                "message"=>"Unauthorized"
            ],403);
        }

        if(!$live_session){
            return response()->json([
                "message"=>"Live session not found"
            ],404);
        }


        $review=SessionReview::create([
            "live_session_id"=>$live_session->id,
            "rating"=>$request->rating,
            "review"=>$request->review_text
        ]);

        return response()->json([
            "message"=>"Review submitted successfully",
            "session_review"=>$review
        ]);
    }
}
