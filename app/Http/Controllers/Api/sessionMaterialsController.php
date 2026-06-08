<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use App\Models\SessionMaterial;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class sessionMaterialsController extends Controller
{
    public function uploadSessionMaterials(Request $request, SupabaseStorageService $storage){
        $request->validate([
            "live_session_id"=>"required|exists:live_sessions,id",
            "files"=>"required|array",
            "files.*.fileTitle"=>"required|string",
            "files.*.fileType"=>"required|string",
            "files.*.file"=>"required|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,webp|max:20480"
        ]);

        $user=$request->user();
        if(!$user){
            return response()->json([
                "message"=>"unauthenticated user"
            ],401);
        }

        $teacher=$user->teacher;
        if(!$teacher){
            return response()->json([
                "message"=>"Unautharized Access"
            ],403);
        }

        $session=LiveSession::findOrFail($request->live_session_id);
        
        $materials=[];
        $filesInput = $request->input('files');

        DB::transaction(function () use($request,$storage,$session,&$materials, $filesInput) {
            foreach ($filesInput as $index => $fileData){
                $file = $request->file("files.$index.file");
                
                if($file){
                    $path=$storage->uploadSessionMaterials($file,$session->id,$fileData['fileTitle']);
                    if($path){
                        $material=SessionMaterial::create([
                            "live_session_id"=>$session->id,
                            "title"=>$fileData['fileTitle'],
                            "file_type"=>$fileData['fileType'],
                            "file_url"=>$path
                        ]);
                        array_push($materials,$material);
                    }
                }
            }
        });

        return response()->json([
            "message"=>"Materials uploaded successfully",
            "session_materials"=>$materials
        ],200);
    }

    public function getSessionMaterials(Request $request,SupabaseStorageService $storage){
        $request->validate([
            "session_id"=>"required|exists:live_sessions,id"
        ]);

        $user=$request->user();
        if(!$user){
            return response()->json([
                "message"=>"unauthenticated user"
            ],401);
        }

        $session=LiveSession::with('sessionMaterials')->where('id',$request->session_id)->first();
        $materials=$session->sessionMaterials;
        
        if($materials->isEmpty()){
            return response()->json([
                "message"=>"no materials found"
            ],200);
        }

        $materials->each(function($material) use($storage){
            $material->file_url=$storage->getTemporaryUrl($material->file_url);
        });

        return response()->json([
            "message"=>"Materials retrieved successfully",
            "session_materials"=>$materials
        ],200);
    }

    public function deleteSessionMaterial(Request $request, SupabaseStorageService $storage ){
        $request->validate([
            'sessionMaterialId'=>'required|exists:session_materials,id'
        ]);

        $user=$request->user();
        if(!$user){
            return response()->json([
                "message"=>"unauthenticated user"
            ],401);
        }

        $teacher=$user->teacher;
        if(!$teacher){
            return response()->json([
                "message"=>"Unautharized Access"
            ],403);
        }

        $sessionMaterial=SessionMaterial::findOrFail($request->sessionMaterialId);
        
        $deleted=DB::transaction(function() use($storage,$sessionMaterial){
            if($storage->deleteSessionMaterial($sessionMaterial->file_url)){
                return SessionMaterial::destroy($sessionMaterial->id);
            }
            return false;
        });

        if($deleted){
            return response()->json([
                "message"=>"material deleted successfully",
            ],200);
        }else{
            return response()->json([
                "message"=>"deletion failed",
            ],500);
        }
    }
}
