<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiChat;
use Illuminate\Http\Request;

class aiChatController extends Controller
{
    public function getChats(Request $request){
        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthorized Access"
            ],401);
        }
        $chats=AiChat::with('user')->where('user_id',$user->id)->get();
        if($chats->isEmpty()){
            return response()->json([
                "message"=>"No chats found"
            ],404);
        }
        return response()->json([
            "message"=>"Chats retrieved successfully",
            "chats"=>$chats
        ],200);
    }
}
