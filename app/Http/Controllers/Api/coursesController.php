<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseSection;
use App\Services\SubscriptionService;
use App\Services\SupabaseStorageService;
use Exception;
use Illuminate\Contracts\Auth\SupportsBasicAuth;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class coursesController extends Controller
{
    public function createCourse(Request $request,SupabaseStorageService $storage,SubscriptionService $subscriptionService){
        $request->validate([
            "category_id"=>"required|exists:categories,id",
            "title"=>"required|string",
            "description"=>"required|string",
            "thumbnail"=>"required|file|mimes:jpeg,png,jpg,webp|max:5120",
            "language"=>"required|string",
            "price"=>"required|numeric",

            "sections"=>"required|array",
            "sections.*.title"=>"required|string",
            "sections.*.order"=>"required|integer",

            "sections.*.materials"=>"required|array",
            "sections.*.materials.*.title"=>"required|string",
            "sections.*.materials.*.type"=>"required",
            "sections.*.materials.*.file"=>"required|file",
            "sections.*.materials.*.size"=>"required|integer",
        ]);

        $user=$request->user();
        if(!$user){
            return response()->json([
                "success"=>false,
                "message"=>"You are not authorized to complete this action",
            ],401);
        }

        $teacher = $user->teacher;
        if(!$teacher){
            return response()->json([
                "success"=>false,
                "message"=>"You are not authorized to complete this action",
            ],403);
        }
        $canCreateCourse=$subscriptionService->canCreateCourse($user);
        if(!$canCreateCourse){
            return response()->json([
                "success"=>false,
                "message"=>"You exceeded the limit of course publishing.Upgrade your Subscription to publish more courses",
            ],403);
        }

        $course=DB::transaction(function() use ($request,$teacher,$storage){
            $thumbnailPath=$storage->uploadthumbnail(
                $request->thumbnail,
                $request->title,
            );
            if(!$thumbnailPath){
                throw new \Exception("Failed to upload thumbnail");
            }
            $course=Course::create([
                "teacher_id"=>$teacher->id,
                "category_id"=>$request->category_id,
                "title"=>$request->title,
                "description"=>$request->description,
                "thumbnail"=>$thumbnailPath,
                "language"=>$request->language,
                "price"=>$request->price,
            ]);

            foreach ($request->sections as $sectionData) {
                $section=CourseSection::create([
                    "course_id"=>$course->id,
                    "title"=>$sectionData['title'],
                    "order"=>$sectionData['order'],
                ]);

                foreach ($sectionData['materials'] as $materialData) {
                    $materialPath=$storage->uploadSectionMaterials(
                        $materialData['file'],
                        $course->title,
                        $section->title,
                        $materialData['title']
                    );

                    CourseMaterial::create([
                        "section_id"=>$section->id,
                        "title"=>$materialData['title'],
                        "path"=>$materialPath,
                        "type"=>$materialData['type'],
                        "size"=>$materialData['size'],
                    ]);
                }
            }
            
            // make course published after successfully created
            $course->status="published";
            $course->save();

            return $course;
        });

        if($course->thumbnail){
            $course->thumbnail=$storage->getPublicUrl($course->thumbnail);
        }

        return response()->json([
            "success"=>true,
            "message"=>"Course created successfully",
            "course"=>$course
        ],201);
    }

    public function saveDraftCourse(Request $request,SupabaseStorageService $storage){
        $request->validate([
            "category_id"=>"nullable|exists:categories,id",
            "title"=>"nullable|string",
            "description"=>"nullable|string",
            "thumbnail"=>"nullable|file|mimes:jpeg,png,jpg,webp|max:5120",
            "language"=>"nullable|string",
            "price"=>"nullable|numeric",

            "sections"=>"nullable|array",
            "sections.*.title"=>"nullable|string",
            "sections.*.order"=>"nullable|integer",

            "sections.*.materials"=>"nullable|array",
            "sections.*.materials.*.title"=>"nullable|string",
            "sections.*.materials.*.type"=>"nullable",
            "sections.*.materials.*.file"=>"nullable|file",
            "sections.*.materials.*.size"=>"nullable|integer",
        ]);

        $user=$request->user();
        if(!$user){
            return response()->json([
                "success"=>false,
                "message"=>"Unauthenticated",
            ],401);
        }
        $teacher=$user->teacher;
        if(!$teacher){
            return response()->json([
                "success"=>false,
                "message"=>"You are not authorized to complete this action"
            ],403);
        }

        $course=DB::transaction(function () use ($request,$storage,$teacher){
            $thumbnailPath=null;
            if($request->hasFile('thumbnail')){
                $thumbnailPath=$storage->uploadthumbnail(
                    $request->thumbnail,
                    $request->title?? "Unknown Title"
                );
            }else{
                $thumbnailPath="/src/assets/default-thumbnail.jfif";
            }

            $course=Course::create([
                "teacher_id"=>$teacher->id,
                "category_id"=>$request->category_id,
                "title"=>$request->title ?? 'Draft Course',
                "description"=>$request->description ?? '',
                "thumbnail"=>$thumbnailPath,
                "language"=>$request->language ?? 'English',
                "status"=>"draft",
                "price"=>$request->price ?? 0,
            ]);

            if($request->sections){
                foreach ($request->sections as $sectionData) {
                    $section=CourseSection::create([
                        "course_id"=>$course->id,
                        "title"=>$sectionData['title'],
                        "order"=>$sectionData['order'],
                    ]);

                    if($sectionData['materials']){
                        foreach ($sectionData['materials'] as $materialData) {
                            $materialPath=$storage->uploadSectionMaterials(
                                $materialData['file'],
                                $course->title,
                                $section->title,
                                $materialData['title']
                            );

                            CourseMaterial::create([
                                "section_id"=>$section->id,
                                "title"=>$materialData['title'],
                                "path"=>$materialPath,
                                "type"=>$materialData['type'],
                                "size"=>$materialData['size'],
                            ]);
                        }
                    }
                }
            }
            return $course;
        });

        if($course->thumbnail && $course->thumbnail !== "/src/assets/default-thumbnail.jfif"){
            $course->thumbnail=$storage->getPublicUrl($course->thumbnail);
        }

        return response()->json([
            "message"=>"Course saved as draft successfully",
            "course"=>$course
        ],201);
    }

    public function editCourse(Request $request, SupabaseStorageService $storage)
    {
        $request->validate([
            "course_id" => "required|exists:courses,id",
            "category_id" => "required|exists:categories,id",
            "title" => "required|string",
            "description" => "required|string",
            "thumbnail" => "nullable|file|mimes:jpeg,png,jpg,webp|max:5120",
            "language" => "required|string",
            "price" => "required|numeric",

            "sections" => "nullable|array",
            "sections.*.id" => "nullable",
            "sections.*.title" => "required|string",
            "sections.*.order" => "required|integer",

            "sections.*.materials" => "nullable|array",
            "sections.*.materials.*.id" => "nullable",
            "sections.*.materials.*.title" => "required|string",
            "sections.*.materials.*.type" => "required",
            "sections.*.materials.*.file" => "nullable|file",
            "sections.*.materials.*.size" => "nullable|integer",
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                "message" => "Unauthenticated"
            ], 401);
        }

        $teacher = $user->teacher;
        if (!$teacher) {
            return response()->json([
                "message" => "Unauthorized Access"
            ], 403);
        }

        $course = Course::where('id', $request->course_id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if (!$course) {
            return response()->json([
                "message" => "Course not found or unauthorized to edit"
            ], 404);
        }

        $updatedCourse = DB::transaction(function () use ($request, $storage, $course) {
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $storage->uploadThumbnail(
                    $request->file('thumbnail'),
                    $request->title ?? 'Unknown Title'
                );
                $course->thumbnail = $thumbnailPath;
            }

            $course->category_id = $request->category_id;
            $course->title = $request->title;
            $course->description = $request->description;
            $course->language = $request->language;
            $course->price = $request->price;
            $course->save();

            $providedSectionIds = [];

            if ($request->sections) {
                foreach ($request->sections as $sectionData) {
                    $section = null;

                    if (isset($sectionData['id']) && $sectionData['id']) {
                        $section = CourseSection::where('id', $sectionData['id'])
                            ->where('course_id', $course->id)
                            ->first();
                    }

                    if ($section) {
                        $section->update([
                            'title' => $sectionData['title'],
                            'order' => $sectionData['order'],
                        ]);
                    } else {
                        $section = CourseSection::create([
                            'course_id' => $course->id,
                            'title' => $sectionData['title'],
                            'order' => $sectionData['order'],
                        ]);
                    }

                    $providedSectionIds[] = $section->id;
                    $providedMaterialIds = [];

                    if (isset($sectionData['materials'])) {
                        foreach ($sectionData['materials'] as $materialData) {
                            // Initialize material handling
                            $material = null;
                            $materialPath = null;

                            // Attempt to fetch existing material if ID provided
                            if (isset($materialData['id']) && $materialData['id']) {
                                $material = CourseMaterial::where('id', $materialData['id'])
                                    ->where('section_id', $section->id)
                                    ->first();
                            }

                            // Preserve existing path if material exists
                            if ($material != null && $materialPath == null) {
                                $materialPath = $material->path;
                            }

                            if (isset($materialData['file']) && $materialData['file'] instanceof UploadedFile) {
                                $materialPath = $storage->uploadSectionMaterials(
                                    $materialData['file'],
                                    $course->title,
                                    $section->title,
                                    $materialData['title']
                                );
                            }

                            if ($material !== null) {
                                // Update existing material
                                $material->title = $materialData['title'];
                                $material->type = $materialData['type'];
                                if (isset($materialData['size'])) {
                                    $material->size = $materialData['size'];
                                }
                                // Only overwrite path if a new file was uploaded
                                if ($materialPath !== null && $materialPath !== $material->path) {
                                    $material->path = $materialPath;
                                }
                                $material->save();
                                $providedMaterialIds[] = $material->id;
                            } else {
                                // Create new material record
                                // Ensure path is not null to satisfy DB constraint
                                if(!$materialPath){
                                    throw new Exception("Material path is required for material ". $materialData['title']);
                                }
                                $newMaterial = CourseMaterial::create([
                                    "section_id" => $section->id,
                                    "title" => $materialData['title'],
                                    "type" => $materialData['type'],
                                    "path" => $materialPath,
                                    "size" => $materialData['size'] ?? 0,
                                ]);
                                $providedMaterialIds[] = $newMaterial->id;
                            }
                        }
                    }

                    // Delete materials not included in the request
                    // Only delete if the materials key was explicitly sent in the request
                    if (isset($sectionData['materials'])) {
                        $deletedMaterial=CourseMaterial::where('section_id', $section->id)
                            ->whereNotIn('id', $providedMaterialIds)
                            ->get();
                        
                        foreach($deletedMaterial as $material){
                            $storage->deleteSessionMaterial($material->path);
                        }

                        CourseMaterial::where('section_id', $section->id)
                            ->whereNotIn('id', $providedMaterialIds)
                            ->delete();
                    }
                }
            }

            // Delete sections not included in the request
            CourseSection::where('course_id', $course->id)
                ->whereNotIn('id', $providedSectionIds)
                ->delete();

            return $course->load('sections.materials');
        });

        // load course thumbnail
        if($updatedCourse->thumbnail){
            $updatedCourse->thumbnail=$storage->getPublicUrl($updatedCourse->thumbnail);
        }

        return response()->json([
            "message" => "Course updated successfully",
            "course" => $updatedCourse
        ], 200);
    }

    public function getTeacherCourses(Request $request, SupabaseStorageService $storage){
        $user=$request->user();
        $teacher=$user->teacher;
        if(!$user || !$teacher){
            return response()->json([
                "success"=>false,
                "message"=>"Unautharized Access"
            ],403);
        }

        $courses=$teacher->courses()->with('category')->get();
        // load first subscription and plan to avoid n+1 query issue
        $user->load('subscription.plan');
        $maxCoursesAllowed=$user->subscription->plan->features['max_courses'];

        // if($courses->isEmpty()){
        //     return response()->json([
        //         "success"=>false,
        //         "message"=>"No courses found"
        //     ],404);
        // }

        return response()->json([
            "success"=>true,
            "message"=>"Courses fetched successfully",
            "courses"=>$courses,
            "max_courses_allowed"=>$maxCoursesAllowed
        ],200);
    }

    public function getCourses(Request $request, SupabaseStorageService $storage){
        $courses=Course::query()
                ->with('teacher.user','category')
                ->withCount('courseReviews')
                ->withAvg('courseReviews','rating')
                ->where('status','published')
                ->orderBy('created_at','desc')
                ->paginate(10);

        return response()->json([
            "message"=>"Courses fetched successfully",
            "courses"=>$courses->items(),
            "pagination"=>[
                "current_page"=>$courses->currentPage(),
                "per_page"=>$courses->perPage(),
                "total"=>$courses->total(),
                "last_page"=>$courses->lastPage(),
                "from"=>$courses->firstItem(),
                "to"=>$courses->lastItem(),
            ]
        ],200);
    }

    public function getCourseWithMaterialsById($id,Request $request,SupabaseStorageService $storage){
        $course=Course::whereId($id)
                        ->with('teacher.user','category','sections.materials','courseReviews.student.user')
                        ->withCount('enrollments')
                        ->first();
        if(!$course){
            return response()->json([
                'message'=>'No course found'
            ],404);
        }

        if($course->sections){
            foreach($course->sections as $sectionData){
                if($sectionData->materials){
                    foreach($sectionData->materials as $materialData){
                        if($materialData->path){
                            $materialData->path=$storage->getTemporaryUrl($materialData->path);
                        }
                    }
                }
            }
        }

        return response()->json([
            "message"=>"Course fetched successfully",
            "course"=>$course
        ],200);
    }

    public function getCourseById($id,Request $request,SupabaseStorageService $storage){
        $course=Course::whereId($id)
                ->with('teacher.user','category','sections')
                ->withCount('courseReviews')
                ->withAvg('courseReviews','rating')
                ->first();

        if(!$course){
            return response()->json([
                'message'=>'No course found'
            ],404);
        }

        return response()->json([
            "message"=>"Course fetched successfully",
            "course"=>$course
        ],200);
    }

    public function getCoursesByFilters(Request $request,SupabaseStorageService $storage){
        $request->validate([
            "category_id"=>"nullable | exists:categories,id",
            "price_range"=>"array | nullable| size:2",
        ]);

        $courses=Course::query()
                    ->with('category','teacher.user')
                    ->where('status','published')
                    ->orderBy('created_at','desc');

        if($request->has('category_id')){
            $courses->where('category_id',$request->category_id);
        }

        if($request->has('price_range') && count($request->price_range) == 2){
            $courses->whereBetween('price',$request->price_range);
        }

        $courses=$courses->paginate(10);

        return response()->json([
            "message"=>"Courses fetched successfully",
            "courses"=>$courses->items(),
            "pagination"=>[
                "current_page"=>$courses->currentPage(),
                "per_page"=>$courses->perPage(),
                "total"=>$courses->total(),
                "last_page"=>$courses->lastPage(),
            ]
        ],200);
    }

    public function changeCourseStatus(Request $request,SupabaseStorageService $storage){
        $request->validate([
            "course_id"=>"required|exists:courses,id",
            "status"=>"required|in:published,draft"
        ]);

        $user=$request->user();

        if(!$user || !$user->teacher){
            return response()->json([
                "success"=>false,
                "message"=>"Unauthorized Access"
            ],403);
        }

        $course=$user->teacher->courses()->whereId($request->course_id)->first();
        if(!$course){
            return response()->json([
                "success"=>false,
                "message"=>"No Course Found"
            ],404);
        }

        if($course->status==$request->status){
            return response()->json([
                "success"=>false,
                "message"=>"Course is already {$request->status}"
            ],400);
        }
        $course->update([
            "status"=>$request->status
        ]);

        return response()->json([
            "success"=>true,
            "message"=>"Course {$request->status} successfully",
            "course"=>$course
        ],200);
    }

    public function downloadCourseMaterial($id) {
        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated"
            ],401);
        }
        $student=$user->student;
        if(!$student){
            return response()->json([
                "message"=>"Unauthorized Access"
            ],403);
        }

        $material = CourseMaterial::with('section.course')->find($id);
        if (!$material || !$material->path) {
            abort(404, 'Material not found');
        }
        $course=$material->section->course;
        if(!$course){
            return response()->json([
                "message"=>"Course not found"
            ],404);
        }

        $isEnrolled=$student->enrollments()
            ->where('course_id',$course->id)
            ->exists();
        if(!$isEnrolled){
            return response()->json([
                "message"=>"You are not enrolled in this course"
            ],403);
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('s3');
        if (!$disk->exists($material->path)) {
            abort(404, 'File not found in storage');
        }

        $fileContent = $disk->get($material->path);
        
        // Get original extension
        $ext = pathinfo($material->path, PATHINFO_EXTENSION);
        $fileName = $material->title;
        if (!str_ends_with(strtolower($fileName), '.' . strtolower($ext))) {
            $fileName .= '.' . $ext;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($fileContent) ?: 'application/octet-stream';

        // return response($fileContent, 200, [
        //     'Content-Type' => $mimeType,
        //     'Content-Disposition' => 'attachment; filename="' . addslashes($fileName) . '"',
        // ]);

        // return response()->download($material->path,$fileName);

        return response()->stream(function() use ($disk,$material){
            echo $disk->get($material->path);
        },200,[
            "Content-Type"=>$mimeType,
            "Content-Disposition"=>'attachment;filename="'.addslashes($fileName).'"',
        ]);
    }
}
