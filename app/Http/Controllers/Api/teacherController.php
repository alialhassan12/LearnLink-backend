<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;

class teacherController extends Controller
{
    public function teacherProfile(Request $request,SupabaseStorageService $storage){
        $user=$request->user();
        if(!$user){
            return response()->json([
                'message'=>'User not found',
            ],404); 
        }
        $teacher=Teacher::where('user_id',$user->id)->with('user','availabilities')->first();
        if(!$teacher){
            return response()->json([
                'message'=>'Teacher not found',
            ],404); 
        }

        return response()->json([
            'message'=>'Teacher profile found successfully',
            'teacher'=>$teacher,
        ],200); 
    }

    public function teacherUpdate(Request $request,SupabaseStorageService $storage){
        // merge json fields
        $request->merge([
            'subjects' => $request->subjects
                ? json_decode($request->input('subjects'), true)
                : [],

            'languages' => $request->languages
                ? json_decode($request->input('languages'), true)
                : [],

            'availability' => $request->availability
                ? json_decode($request->input('availability'), true)
                : [],
        ]);

        $request->validate([
            "name"=>"required|string|max:255",
            "headline"=>"string|nullable|max:255",
            "location"=>'string|nullable|max:255',
            "bio"=>"string|nullable",
            "subjects"=>"array|nullable",
            "languages"=>"array|nullable",
            "hourly_rate"=>"required|numeric|min:0",
            "avatar"=>"nullable|file|mimes:jpeg,png,jpg,gif|max:2048",
            "availability"=>"nullable|array",
            "availability.*.day_of_week"=>"string|max:255",
            "availability.*.start_time"=>"date_format:H:i",
            "availability.*.end_time"=>"date_format:H:i|after:availability.*.start_time",
        ]);

        $user=$request->user();
        $teacher=$user->teacher;
        if(!$teacher){
            return response()->json([
                'message'=>'Unauthorized Access',
            ],401); 
        }

        if($teacher->user_id != $user->id){
            return response()->json([
                'message'=>'Unauthorized Access',
            ],401); 
        }

        if($request->hasFile('avatar')){
            $avatar=$request->file('avatar');
            $avatarPath=$storage->uploadAvatar($avatar,$user->id,$user->avatar);
            $user->update([
                'name'=>$request->name,
                'avatar'=>$avatarPath,
            ]);
        }else{
            $user->update([
                'name'=>$request->name,
            ]);
        }
        
        $teacherData = [
            'headline'=>$request->headline,
            'location'=>$request->location,
            'bio'=>$request->bio,
            'hourly_rate'=>$request->hourly_rate,
        ];

        if($request->has('subjects') && count($request->subjects)>0){
            $teacherData['subjects'] = $request->subjects;
        }
        if($request->has('languages') && count($request->languages)>0){
            $teacherData['languages'] = $request->languages;
        }

        $teacher->fill($teacherData);
        
        if($request->has('availability')){
            $teacher->availabilities()->delete();
            foreach($request->availability as $slot){
                $teacher->availabilities()->create([
                    'day_of_week'=>$slot['day_of_week'],
                    'start_time'=>$slot['start_time'],
                    'end_time'=>$slot['end_time'],
                ]);
            }
        }

        $user->save();
        $teacher->save();

        $teacher->load('user');
        
        return response()->json([
            'message'=>'Profile updated successfully',
            'teacher'=>$teacher,
        ],200); 
    }

    public function getTeachers(Request $request,SupabaseStorageService $storage){
        $teachers=Teacher::query()
                ->with('user')
                ->withCount('publishedCourses')
                ->orderBy('created_at','desc')
                ->paginate(10)
                ->through(function($teacher) use ($storage){
                    if($teacher->user->avatar){
                        $teacher->user->avatar=$storage->getPublicUrl($teacher->user->avatar);
                    }
                    return [
                        'id'=>$teacher->id,
                        'name'=>$teacher->user->name,
                        'email'=>$teacher->user->email,
                        'avatar'=>$teacher->user->avatar,
                        'bio'=>$teacher->bio,
                        'headline'=>$teacher->headline,
                        'hourly_rate'=>$teacher->hourly_rate,
                        'subjects'=>$teacher->subjects,
                        'languages'=>$teacher->languages,
                        'created_at'=>$teacher->user->created_at,
                        'updated_at'=>$teacher->user->updated_at,
                        'courses_count'=>$teacher->published_courses_count,
                    ];
                });

        return response()->json([
            'message'=>'Teachers fetched successfully',
            'teachers'=>$teachers->items(),
            'pagination'=>[
                'current_page'=>$teachers->currentPage(),
                'last_page'=>$teachers->lastPage(),
                'per_page'=>$teachers->perPage(),
                'total'=>$teachers->total(),
                'from'=>$teachers->firstItem(),
                'to'=>$teachers->lastItem(),
            ],
        ],200);
    }

    public function getSubjects(){
        $subjects=Teacher::pluck('subjects')
                ->flatten()
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        
        return response()->json([
            'message'=>'Subjects fetched successfully',
            'subjects'=>$subjects,
        ],200); 
    }

    public function getLanguages(){
        $languages=Teacher::pluck('languages')
                ->flatten()
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        
        return response()->json([
            'message'=>'Languages fetched successfully',
            'languages'=>$languages,
        ],200); 
    }
    
    public function getTeacherById(Request $request,SupabaseStorageService $storage,$id){
        $teacher=Teacher::with([
                'user',
                'availabilities',
                'publishedCourses'=>function($query){
                    $query->orderBy('created_at','desc')->limit(1);
                }
        ])->find($id);

        if(!$teacher){
            return response()->json([
                'message'=>'Teacher not found',
            ],404); 
        }

        if($teacher->user->avatar){
            $teacher->user->avatar=$storage->getPublicUrl($teacher->user->avatar);
        }

        if($teacher->publishedCourses->count()>0){
            foreach($teacher->publishedCourses as $course){
                $course->thumbnail=$storage->getPublicUrl($course->thumbnail);
            }
        }

        return response()->json([
            'message'=>'Teacher profile found successfully',
            'teacher'=>$teacher
        ],200); 
    }

    public function getTeachersByFilters(Request $request, SupabaseStorageService $storage){
        $request->validate([
            'subjects'=>'array|nullable',
            'language'=>'string|nullable',
            'hourlyRate'=>'array|nullable|size:2',
            'rating'=>'numeric|nullable',
        ]);

        $query=Teacher::query()
                ->with('user')
                ->withCount('publishedCourses')
                ->orderBy('created_at','desc');
        
        if($request->has('subjects') && count($request->subjects)>0){
            $query->where(function($q) use ($request){
                foreach($request->subjects as $subject){
                    $q->orWhereJsonContains('subjects', $subject);
                }
            });
        }

        if($request->has('language') && $request->language!="all"){
            $query->whereJsonContains('languages', $request->language);
        }

        if($request->has('hourlyRate') && count($request->hourlyRate)==2){
            $query->whereBetween('hourly_rate',$request->hourlyRate);
        }

        // if($request->has('rating') && $request->rating!=0){
        //     $query->where('rating','>=',$request->rating);
        // }

        $teachers=$query->paginate(10)
                ->through(function($teacher) use ($storage){
                    if($teacher->user->avatar){
                        $teacher->user->avatar=$storage->getPublicUrl($teacher->user->avatar);
                    }
                    return [
                        'id'=>$teacher->id,
                        'name'=>$teacher->user->name,
                        'email'=>$teacher->user->email,
                        'avatar'=>$teacher->user->avatar,
                        'bio'=>$teacher->bio,
                        'headline'=>$teacher->headline,
                        'hourly_rate'=>$teacher->hourly_rate,
                        'subjects'=>$teacher->subjects,
                        'languages'=>$teacher->languages,
                        'created_at'=>$teacher->user->created_at,
                        'updated_at'=>$teacher->user->updated_at,
                        'courses_count'=>$teacher->published_courses_count,
                    ];
                });

        return response()->json([
            'message'=>'Teachers fetched successfully',
            'teachers'=>$teachers->items(),
            'pagination'=>[
                'current_page'=>$teachers->currentPage(),
                'last_page'=>$teachers->lastPage(),
                'per_page'=>$teachers->perPage(),
                'total'=>$teachers->total(),
                'from'=>$teachers->firstItem(),
                'to'=>$teachers->lastItem(),
            ],
        ],200); 
    }
}

