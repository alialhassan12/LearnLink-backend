<?php

namespace App\Services;

use App\Models\Conversation;
use Illuminate\Support\Facades\DB;

class ConversationService{
    public static function findOrCreateDirectConversation(
        int $user1,
        int $user2
    ):Conversation{
        $conversation=Conversation::query()
            ->where('type','direct')
            ->whereHas('participants',fn($q)=>$q->where('user_id',$user1))
            ->whereHas('participants',fn($q)=>$q->where('user_id',$user2))
            ->first();

        if($conversation){
            return $conversation;
        }

        $conversation=Conversation::create([
            'type'=>'direct',
        ]);

        DB::table('conversation_participants')->insert([
            [
                'conversation_id'=> $conversation->id,
                'user_id'=>$user1,
                'created_at'=>now(),
                'updated_at'=>now()
            ],
            [
                'conversation_id'=> $conversation->id,
                'user_id'=>$user2,
                'created_at'=>now(),
                'updated_at'=>now()
            ],
        ]);
        return $conversation;
    }
}