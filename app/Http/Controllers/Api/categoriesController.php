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
        $category=Category::withCount('courses')->whereId($id)->first();
        if(!$category){
            return response()->json([
                "message"=>"Category not found"
            ],404);
        }
        if($category->courses_count > 0){
            return response()->json([
                "message"=>"Category has courses, cannot delete"
            ],403);
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
