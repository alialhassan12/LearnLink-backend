<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;

class adminController extends Controller
{
    public function getUsers(Request $reques,SupabaseStorageService $storage){
        $users=User::with("subscription.plan")->where('role','!=','admin')->orderBy("created_at")
            ->paginate(4)->through(function($users) use($storage){
                if($users->avatar){
                    $users->avatar=$storage->getPublicUrl($users->avatar);
                }
                return $users;
            });
        
        return response()->json([
            "message"=>"Users retrieved successfully",
            "users"=>$users,
            "pagination"=>[
                "current_page"=>$users->currentPage(),
                "last_page"=>$users->lastPage(),
                "per_page"=>$users->perPage(),
                "total"=>$users->total(),
                "from"=>$users->firstItem(),
                "to"=>$users->lastItem(),
            ]
        ]);
    }

    public function suspendUser(Request $request){
        $request->validate([
            "user_id"=>"required|exists:users,id"
        ]);

        $targetedUser=User::whereId($request->user_id)->first();
        if(!$targetedUser){
            return response()->json([
                "message"=>"User Not Found",
            ],404);
        }
        if($targetedUser->role=='admin'){
            return response()->json([
                "message"=>"Admin Cannot be Suspended",
            ],403);
        }

        $targetedUser->status='inactive';
        $targetedUser->save();

        return response()->json([
            "message"=>"User Suspended",
            "user"=>$targetedUser
        ]);
    }

    public function activateUser(Request $request){
        $request->validate([
            "user_id"=>"required|exists:users,id"
        ]);

        $targetedUser=User::whereId($request->user_id)->first();
        if(!$targetedUser){
            return response()->json([
                "message"=>"User Not Found",
            ],404);
        }
        if($targetedUser->role=='admin'){
            return response()->json([
                "message"=>"Admin's status cannot be changed",
            ],403);
        }

        $targetedUser->status='active';
        $targetedUser->save();

        return response()->json([
            "message"=>"User Activated",
            "user"=>$targetedUser
        ]);
    }
}
