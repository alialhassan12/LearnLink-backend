<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\Plan;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\Teacher;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class authController extends Controller
{
    public function register(Request $request){
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:student,teacher',
        ]);

        $user=DB::transaction(function() use ($request){
            $user=User::create([
                'name'=>$request->name,
                'email'=>$request->email,
                'password'=>$request->password,
                'role'=>$request->role,
            ]);
            if($request->role=='student'){
                Student::create([
                    'user_id'=>$user->id,
                ]);
            }else if($request->role=='teacher'){
                Teacher::create([
                    'user_id'=>$user->id,
                ]);
            }
            $plan=Plan::where('is_free',true)->first();
            if($plan){
                Subscription::create([
                    'user_id'=>$user->id,
                    'plan_id'=>$plan->id,
                    'start_at'=>now(),
                    'end_at'=>now()->addDays(30),
                ]);
            }

            return $user;
        });

        $token=$user->createToken('api_token')->plainTextToken;

        // send welcome email
        Mail::to($user->email)->queue(new WelcomeMail($user));

        if($user->role == 'student' || $user->role == 'teacher'){
            $user->load('subscription.plan');
        }

        return response()->json([
            'message'=>'User registered successfully',
            'user'=>$user,
            'token'=>$token,
        ],201);
    }

    public function login(Request $request, SupabaseStorageService $storage){
        $request->validate([
            'email'=>'required|string|email',
            'password'=>'required|string|min:8',
        ]);

        $user=User::where('email', $request->email)->first();
        if(!$user || !Hash::check($request->password, $user->password)){
            return response()->json([
                'message'=>'Invalid credentials',
            ],401);
        }

        if($user->status == "inactive"){
            return response()->json([
                'message'=>'Your account has been suspended. Contact support for more information',
            ],403);
        }
        
        $token=$user->createToken('api_token')->plainTextToken;
        
        if($user->role == 'student' || $user->role == 'teacher'){
            $user->load('subscription.plan');
        }

        return response()->json([
            'message'=>'User logged in successfully',
            'user'=>$user,
            'token'=>$token,
        ],200); 
    }

    public function logout(Request $request){
        $user=$request->user();
        if(!$user){
            return response()->json([
                'message'=>'User not found',
            ],404); 
        }
        $user->currentAccessToken()->delete();
        return response()->json([
            'message'=>'User logged out successfully',
        ],200); 
    }

    public function checkAuth(Request $request, SupabaseStorageService $storage){
        $user=$request->user();
        if(!$user){
            return response()->json([
                'message'=>'User not found',
            ],404); 
        }

        if($user->status == "inactive"){
            return response()->json([
                'message'=>'Your account has been suspended. Contact support for more information',
            ],403);
        }

        if($user->role == 'student' || $user->role == 'teacher'){
            $user->load('subscription.plan');
        }

        return response()->json([
            'user'=>$user,
        ],200); 
    }
}
