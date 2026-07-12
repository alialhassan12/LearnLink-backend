<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class notificationController extends Controller
{
    public function getNotificationHistory(){
        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "Unauthenticated"
            ],401);
        }
        $notifications=Notification::where('user_id',$user->id)->orderby('created_at','desc')->get();
        return response()->json([
            "message"=>"Successfully fetched notifications",
            "notifications"=>$notifications
        ],200);
    }
}
