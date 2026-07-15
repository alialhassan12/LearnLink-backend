<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CourseEnrollment;
use App\Models\LiveSession;
use App\Models\Teacher;
use App\Services\SupabaseStorageService;
use Carbon\Carbon;
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
            $slots=[];
            foreach($request->availability as $slot){
                $slots[]=[
                    'teacher_id'=>$teacher->id,
                    'day_of_week'=>$slot['day_of_week'],
                    'start_time'=>$slot['start_time'],
                    'end_time'=>$slot['end_time'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if(!empty($slots)){
                $teacher->availabilities()->insert($slots);
            }
        }

        $teacher->save();

        $teacher->load('user','availabilities');
        
        return response()->json([
            'message'=>'Profile updated successfully',
            'teacher'=>$teacher,
        ],200); 
    }

    public function getTeachers(Request $request,SupabaseStorageService $storage){
        $teachers=Teacher::query()
                ->select("teachers.*")
                ->join('users','teachers.user_id','=','users.id')
                ->join('subscriptions','users.id','=','subscriptions.user_id')
                ->join('plans','subscriptions.plan_id','=','plans.id')
                ->with('user.subscription.plan','liveSessions.sessionReview')
                ->withCount('publishedCourses')
                ->orderBy('plans.features->search_priority', 'desc')
                ->orderBy('teachers.created_at','desc')
                ->paginate(10);

        // Transform the data to add the average rating to each teacher
        $teachers->getCollection()->transform(function ($teacher) {
            // Get the reviews of teacher from liveSessions
            $reviews=$teacher->liveSessions->pluck('sessionReview')->filter();
            
            $teacher->avg_rating = round($reviews->avg('rating')??0,1);
            $teacher->review_count = $reviews->count();
            
            return $teacher;
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
                },
                'liveSessions.sessionReview'
        ])->find($id);

        // calculate avg rating and review count
        $reviews=$teacher->liveSessions->pluck('sessionReview')->filter();
        $teacher->avg_rating=round($reviews->avg('rating')??0,1);
        $teacher->review_count=$reviews->count();

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

    public function getTeachersByFilters(Request $request){
        $request->validate([
            'subjects'=>'array|nullable',
            'language'=>'string|nullable',
            'hourlyRate'=>'array|nullable|size:2',
            'rating'=>'numeric|nullable',
            'search_query'=>'string|nullable|max:255'
        ]);

        // Normalize sentinel/default values sent by the frontend
        $subjects = $request->input('subjects', []);
        $language = $request->input('language');
        $search   = trim($request->input('search_query', ''));
        $hourlyRate = $request->input('hourlyRate');

        // Treat "all" as no language filter, empty string as no search
        $hasLanguage  = !empty($language) && $language !== 'all';
        $hasSearch    = $search !== '';
        $hasSubjects  = !empty($subjects);
        $hasHourlyRate= !empty($hourlyRate);

        $query=Teacher::query()
                ->select('teachers.*')
                ->join('users','teachers.user_id','=','users.id')
                ->join('subscriptions','users.id','=','subscriptions.user_id')
                ->join('plans','subscriptions.plan_id','=','plans.id')
                ->with('user.subscription.plan','liveSessions.sessionReview')
                ->withCount('publishedCourses')
                ->when($hasSubjects,
                    function ($query) use ($subjects){
                        $query->where(function($query) use($subjects){
                            foreach($subjects as $subject){
                                $query->orWhereJsonContains('teachers.subjects',$subject);
                            }
                        });
                    }
                )
                ->when($hasLanguage,
                    fn($query)=>$query->whereJsonContains('teachers.languages',$language)
                )
                ->when($hasHourlyRate,
                    fn($query)=>$query->whereBetween('teachers.hourly_rate',$hourlyRate)
                )
                ->when($hasSearch,
                    function($query) use($search){
                        $query->where(function($query) use ($search){
                            $query->where('users.name','ilike',"%$search%")
                            ->orWhere('teachers.headline','ilike',"%$search%")
                            ->orWhereJsonContains('teachers.subjects',$search);
                        });
                    }
                )
                ->orderBy('plans.features->search_priority', 'desc')
                ->orderBy('teachers.created_at','desc');
            
        $teachers=$query->paginate(10);
        
        $teachers->getCollection()->transform(function($teacher){
            $reviews=$teacher->liveSessions->pluck('sessionReview')->filter();
            
            $teacher->avg_rating=round($reviews->avg('rating')??0,1);
            $teacher->review_count=$reviews->count();
            
            return $teacher;
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

    public function teacherDashboard(Request $request){
        $user=auth('sanctum')->user();
        if(!$user){
            return response()->json([
                "message"=>"Unauthenticated"
            ],401);
        }
        
        $teacher=Teacher::withCount([
            'courses'
        ])->where('user_id',$user->id)->first();
        if(!$teacher){
            return response()->json([
                "message"=>"Unauthorized Access"
            ],401);
        }
        
        $upcoming_sessions=LiveSession::with('student.user')
                            ->whereHas('booking',function($q)use($teacher){
                                $q->where('teacher_id',$teacher->id);
                            })
                            ->whereDate('scheduled_date','>=',Carbon::today())
                            ->where('status','booked')
                            ->limit(5)
                            ->get();
        $total_enrollments=CourseEnrollment::whereHas('course',function($q) use($teacher){
            $q->where('teacher_id',$teacher->id);
        })->count();    
        

        $pending_bookings=Booking::where('teacher_id',$teacher->id)
                                    ->with('student.user')
                                    ->where('status','pending')
                                    ->limit(3)
                                    ->get();
        return response()->json([
            "message"=>"Teacher dashboard fetched successfully",
            "data"=>[
                "total_courses"=>$teacher->courses_count,
                "upcoming_sessions"=>$upcoming_sessions,
                "total_enrollments"=>$total_enrollments,
                "pending_bookings"=>$pending_bookings,
            ]
        ]);
    }
}

