<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Conversation;

class conversationsController extends Controller
{
    public function getConversations(Request $request){
        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                'message'=>'Unauthenticated'
            ],401);
        }
        $conversations=$user->conversations->load('participants.user', 'lastMessage.sender');

        
        return response()->json([
            'conversations'=>$conversations,
        ],200);
    }
}
