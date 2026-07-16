<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;

class adminController extends Controller
{
    public function getUsers(Request $request, SupabaseStorageService $storage)
    {
        $users = User::with("subscription.plan")->where('role', '!=', 'admin')->orderBy("created_at", "desc")
            ->paginate(4);

        return response()->json([
            "message" => "Users retrieved successfully",
            "users" => $users,
            "pagination" => [
                "current_page" => $users->currentPage(),
                "last_page" => $users->lastPage(),
                "per_page" => $users->perPage(),
                "total" => $users->total(),
                "from" => $users->firstItem(),
                "to" => $users->lastItem(),
            ]
        ]);
    }

    public function suspendUser(Request $request)
    {
        $request->validate([
            "user_id" => "required|exists:users,id"
        ]);

        $targetedUser = User::whereId($request->user_id)->first();
        if (!$targetedUser) {
            return response()->json([
                "message" => "User Not Found",
            ], 404);
        }
        if ($targetedUser->role == 'admin') {
            return response()->json([
                "message" => "Admin Cannot be Suspended",
            ], 403);
        }

        $targetedUser->status = 'inactive';
        $targetedUser->save();

        return response()->json([
            "message" => "User Suspended",
            "user" => $targetedUser
        ]);
    }

    public function activateUser(Request $request)
    {
        $request->validate([
            "user_id" => "required|exists:users,id"
        ]);

        $targetedUser = User::whereId($request->user_id)->first();
        if (!$targetedUser) {
            return response()->json([
                "message" => "User Not Found",
            ], 404);
        }
        if ($targetedUser->role == 'admin') {
            return response()->json([
                "message" => "Admin's status cannot be changed",
            ], 403);
        }

        $targetedUser->status = 'active';
        $targetedUser->save();

        return response()->json([
            "message" => "User Activated",
            "user" => $targetedUser
        ]);
    }

    public function adminDashboard()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                "message" => "Unauthenticated"
            ], 401);
        }
        if ($user->role != "admin") {
            return response()->json([
                "message" => "Unauthorized"
            ], 403);
        }

        $counts = User::selectRaw("
            SUM(CASE WHEN role != 'admin' THEN 1 ELSE 0 END) as total_users,
            SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as total_teachers,
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as total_students
        ")->first();

        $totalUsers = $counts->total_users;
        $totalTeachers = $counts->total_teachers;
        $totalStudents = $counts->total_students;
        $totalCourses = Course::where('status', 'published')->count();

        $recentUsers = User::where('role', '!=', 'admin')->latest()->take(5)->get();
        $recentCourses = Course::where('status', 'published')->latest()->take(5)->get();

        return response()->json([
            "message" => "Admin Dashboard Data Retrieved Successfully",
            "data" => [
                "totalUsers" => $totalUsers,
                "totalTeachers" => $totalTeachers,
                "totalStudents" => $totalStudents,
                "totalCourses" => $totalCourses,
                "recentUsers" => $recentUsers,
                "recentCourses" => $recentCourses,
            ],
        ]);
    }

    public function adminGetCourses()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                "message" => "Unauthenticated"
            ], 401);
        }
        $courses = Course::with('teacher.user', 'category')
            ->withCount('enrollments')
            ->withCount('courseReviews')
            ->withAvg('courseReviews', 'rating')
            ->paginate(10);

        return response()->json([
            "message" => "Courses Retrieved Successfully",
            "courses" => $courses
        ]);
    }

    public function adminGetCourseDetails($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                "message" => "Unauthenticated"
            ], 401);
        }
        $course = Course::with(
            'teacher.user',
            'category',
            'sections.materials',
            'enrollments.student.user',
            'courseReviews.student.user'
        )
            ->withCount('enrollments')
            ->withCount('courseReviews')
            ->withAvg('courseReviews', 'rating')
            ->find($id);

        if (!$course) {
            return response()->json([
                "message" => "Course Not Found"
            ], 404);
        }

        return response()->json([
            "message" => "Course Details Retrieved Successfully",
            "course" => $course
        ]);
    }

    public function adminChangeCourseStatus(Request $request)
    {
        $request->validate([
            "course_id" => "required|exists:courses,id",
            "status" => "required|in:published,draft"
        ]);

        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                "message" => "Unauthenticated"
            ], 401);
        }
        $course = Course::find($request->course_id);
        if (!$course) {
            return response()->json([
                "message" => "Course Not Found"
            ], 404);
        }
        
        if ($request->status == $course->status) {
            return response()->json([
                "message" => "Course Status Already " . $request->status
            ], 400);
        }

        $course->status = $request->status;
        $course->save();

        return response()->json([
            "message" => "Course Status Changed Successfully",
            "course" => $course
        ]);
    }
}
