<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Request;

class subscriptionsController extends Controller
{
    public function upgradeSubscription(Request $request){
        $request->validate([
            'plan_id'=>'required|exists:plans,id',
        ]);

        $plan=Plan::find($request->plan_id);
        if(!$plan){
            return response()->json([
                'message'=>'Plan not found',
            ],404);
        }

        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                'message'=>'User not authenticated',
            ],401);
        }

        $subscription=Subscription::where('user_id',$user->id)->first();
        if(!$subscription){
            $subscription=Subscription::create([
                'user_id'=>$user->id,
                'plan_id'=>$request->plan_id,
                'start_at'=>Carbon::now(),
                'end_at'=>Carbon::now()->addDays($plan->duration_days),
                'status'=>'active'
            ]);
        }
        else {
            $subscription->update([
                'plan_id'=>$request->plan_id,
                'start_at'=>Carbon::now(),
                'end_at'=>Carbon::now()->addDays($plan->duration_days),
                'status'=>'active'
            ]);
        }
        return response()->json([
            'message'=>'Subscription upgraded successfully',
            'subscription'=>$subscription,
        ],200);
    }
}
