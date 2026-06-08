<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel(
    'conversation.{conversationId}',
    function($user,$conversationId){
        return DB::table('conversation_participants')
            ->where('conversation_id',$conversationId)
            ->where('user_id',$user->id)
            ->exists();
    }
);
