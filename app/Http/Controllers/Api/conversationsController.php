<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Conversation;
use App\Services\SupabaseStorageService;

class conversationsController extends Controller
{
    public function getConversations(Request $request,SupabaseStorageService $storage){
        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                'message'=>'Unauthenticated'
            ],401);
        }
        $conversations=$user->conversations;

        return response()->json([
            'conversations'=>$conversations,
        ],200);
    }
}
