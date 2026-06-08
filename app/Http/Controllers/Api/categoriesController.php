<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class categoriesController extends Controller
{
    public function getCategories(Request $request){
        $categories=Category::where("status","active")->get();
        return response()->json([
            "success"=>true,
            "message"=>"Categories retrieved successfully",
            "categories"=>$categories,
        ],200);
    }

    public function listCategoriesToAdmin(Request $request){
        $user=auth('sanctum')->user();
        if(!$user || $user->role!= "admin"){
            return response()->json([
                "message"=>"Unautharized Access"
            ],403);
        }

        $categories=Category::withCount('courses')->orderBy("created_at")->get();
        return response()->json([
            "message"=>"Categories retrieved successfully",
            "categories"=>$categories,
        ],200);
    }

    public function createCategory(Request $request){
        $request->validate([
            "title"=>"required|string"
        ]);

        $user=auth('sanctum')->user();
        if(!$user || $user->role!= "admin"){
            return response()->json([
                "message"=>"Unautharized Access"
            ],403);
        }

        $category=Category::create([
            "title"=>$request->title
        ]);

        return response()->json([
            "message"=>"Category created successfully",
            "category"=>$category
        ],200);

    }

    public function updateCategory(Request $request){
        $request->validate([
            "category_id"=>"required|exists:categories,id",
            "title"=>"required|string"
        ]);

        $user=auth('sanctum')->user();
        if(!$user || $user->role!= "admin"){
            return response()->json([
                "message"=>"Unautharized Access"
            ],403);
        }
        $category=Category::where("id",$request->category_id)->first();
        if(!$category){
            return response()->json([
                "message"=>"Category Not Found"
            ],404);
        }

        $category->title=$request->title;
        $category->save();

        return response()->json([
            "message"=>"Category updated successfully",
            "category"=>$category
        ]);
    }

    public function deleteCategory($id){
        $user=auth('sanctum')->user();
        if(!$user || $user->role!= "admin"){
            return response()->json([
                "message"=>"Unautharized Access"
            ],403);
        }
        $category=Category::whereId($id)->first();
        if(!$category){
            return response()->json([
                "message"=>"Category not found"
            ],404);
        }
        $category->delete();
        return response()->json([
            "message"=>"Category deleted successfully",
        ],200);
    }

    public function changeCategoryStatus(Request $request){
        $request->validate([
            "category_id"=>"required|exists:categories,id",
            "status"=>"required|in:active,inactive",
        ]);

        $user=auth('sanctum')->user();
        if(!$user || $user->role!= "admin"){
            return response()->json([
                "message"=>"Unautharized Access"
            ],403);
        }
        
        $category=Category::whereId($request->category_id)->first();
        if(!$category){
            return response()->json([
                "message"=>"Category not found"
            ],404);
        }
        
        if($category->status == $request->status){
            return response()->json([
                "message"=>"Category status is already $request->status"
            ],400);
        }

        $category->status=$request->status;
        $category->save();
        return response()->json([
            "message"=>"Category status changed successfully",
            "category"=>$category
        ],200);
    }
}
