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

        $conversations->each(function($conversation)use($storage){
            $conversation->participants->each(function($participant)use($storage){
                if($participant->user->avatar){
                    $participant->user->avatar=$storage->getPublicUrl($participant->user->avatar);
                }
            });
        });

        return response()->json([
            'conversations'=>$conversations,
        ],200);
    }
}
