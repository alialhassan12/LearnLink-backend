<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;

class NotificationService{

    public static function send(User $user,string $title,string $body,array $data=[]){
        if(!$user->loadMissing('deviceTokens')){
            $user->load('deviceTokens');
        }
        
        foreach($user->deviceTokens as $device){
            $response=Http::post(
                'https://exp.host/--/api/v2/push/send',
                [
                    "to"=>$device->push_token,
                    "title"=>$title,
                    "body"=>$body,
                    'data'=>$data
                ]
            );
            logger()->info($response->json());
        }
    }
}