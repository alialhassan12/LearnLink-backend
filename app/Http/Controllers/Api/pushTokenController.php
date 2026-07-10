<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class pushTokenController extends Controller
{
    public function store(Request $request){
        $request->validate([
            "push_token"=>"required|string",
            "platform"=>"required|string"
        ]);

        $user=$request->user();
        if(!$user){
            return response()->json([
                "message"=>"Uauthenticated",
            ],401);
        }

        DeviceToken::updateOrCreate(
            [
                'push_token' => $request->push_token
            ],
            [
                "user_id" => $user->id,
                "platform" => $request->platform
            ]
        );

        return response()->json([
            "message"=>"Push token stored successfully"
        ]);
    }
}
