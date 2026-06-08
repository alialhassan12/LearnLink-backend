<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class plansController extends Controller
{
    public function createPlan(Request $request){
        $request->validate([
            'title'=>'required|string|max:255',
            'description'=>'required|string',
            'type'=>'required|in:teacher,student',
            'features'=>'required|array',

            "features.max_courses"=>"required|integer|min:-1",
            "features.sessions_per_month"=>"required|integer|min:-1",
            "features.ai_tokens_per_month"=>"required|integer|min:0",
            "features.search_priority"=>"required|boolean",

            'duration_days'=>'required|integer|min:-1',
            'price'=>'required|numeric|min:0',
            'is_free'=>'required|boolean',
            'status'=>"required|in:active,inactive"
        ]);

        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated"
            ],401);
        }
        if($user->role!='admin'){
            return response()->json([
                "message"=>"Unauthorized Access"
            ],403);
        }

        $plan=Plan::create([
            "title"=>$request->title,
            "description"=>$request->description,
            "type"=>$request->type,
            "features"=>$request->features,
            "duration_days"=>$request->duration_days,
            "price"=>$request->price,
            "is_free"=>$request->is_free,
            "status"=>$request->status
        ]);

        return response()->json([
            "message"=>"Plan created successfully",
            "plan"=>$plan
        ],201);
    }

    public function getAllPlans(){
        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated"
            ],401);
        }
        if($user->role!="admin"){
            return response()->json([
                "message"=>"Unauthorized Access"
            ],403);
        }

        $plans=Plan::all();
        return response()->json([
            "message"=>"Plans fetched successfully",
            "plans"=>$plans
        ],200);
    }
}
