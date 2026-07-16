<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Booking;
use App\Models\Teacher;
use App\Models\Subscription;
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

        $totalUsers = $counts->total_users ?? 0;
        $totalTeachers = $counts->total_teachers ?? 0;
        $totalStudents = $counts->total_students ?? 0;
        $totalCourses = Course::where('status', 'published')->count();

        $recentUsers = User::where('role', '!=', 'admin')->latest()->take(5)->get();
        $recentCourses = Course::where('status', 'published')->latest()->take(5)->get();

        // ── Charts: 6-month trends (Database Agnostic via PHP grouping) ──────────────────────
        $sixMonthsAgo = now()->subMonths(5)->startOfMonth();

        // 1. User Growth
        $usersList = User::where('role', '!=', 'admin')
            ->where('created_at', '>=', $sixMonthsAgo)
            ->get(['created_at']);
        
        $userGrowth = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $userGrowth[$month] = 0;
        }
        foreach ($usersList as $u) {
            $m = $u->created_at->format('Y-m');
            if (isset($userGrowth[$m])) {
                $userGrowth[$m]++;
            }
        }
        $userGrowthData = [];
        foreach ($userGrowth as $month => $count) {
            $userGrowthData[] = ['month' => $month, 'users' => $count];
        }

        // 2. Course Enrollments
        $enrollmentsList = CourseEnrollment::where('created_at', '>=', $sixMonthsAgo)
            ->get(['created_at']);

        $enrollmentCounts = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $enrollmentCounts[$month] = 0;
        }
        foreach ($enrollmentsList as $e) {
            $m = $e->created_at->format('Y-m');
            if (isset($enrollmentCounts[$m])) {
                $enrollmentCounts[$m]++;
            }
        }
        $enrollmentsData = [];
        foreach ($enrollmentCounts as $month => $count) {
            $enrollmentsData[] = ['month' => $month, 'enrollments' => $count];
        }

        // 3. Revenue Trend (Subscriptions + Bookings)
        $subscriptionsList = Subscription::with('plan')
            ->where('created_at', '>=', $sixMonthsAgo)
            ->get(['created_at', 'plan_id']);

        $bookingsList = Booking::where('status', 'approved')
            ->where('created_at', '>=', $sixMonthsAgo)
            ->get(['created_at', 'price']);

        $revenueTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $revenueTrend[$month] = 0.0;
        }
        foreach ($subscriptionsList as $sub) {
            $m = $sub->created_at->format('Y-m');
            if (isset($revenueTrend[$m])) {
                $price = $sub->plan ? (float)$sub->plan->price : 0.0;
                $revenueTrend[$m] += $price;
            }
        }
        foreach ($bookingsList as $booking) {
            $m = $booking->created_at->format('Y-m');
            if (isset($revenueTrend[$m])) {
                $revenueTrend[$m] += (float)$booking->price;
            }
        }
        $revenueData = [];
        foreach ($revenueTrend as $month => $rev) {
            $revenueData[] = ['month' => $month, 'revenue' => $rev];
        }

        // ── Top Performing Teachers ──────────────────────────────────────────
        $topTeachersByRating = Teacher::with('user')
            ->select('teachers.*')
            ->selectRaw('(SELECT COALESCE(AVG(rating), 0) FROM course_reviews JOIN courses ON courses.id = course_reviews.course_id WHERE courses.teacher_id = teachers.id) as avg_rating')
            ->withCount('approvedBookings')
            ->orderByDesc('avg_rating')
            ->take(5)
            ->get();

        $topTeachersBySessions = Teacher::with('user')
            ->select('teachers.*')
            ->selectRaw('(SELECT COALESCE(AVG(rating), 0) FROM course_reviews JOIN courses ON courses.id = course_reviews.course_id WHERE courses.teacher_id = teachers.id) as avg_rating')
            ->withCount('approvedBookings')
            ->orderByDesc('approved_bookings_count')
            ->take(5)
            ->get();

        // ── Top Performing Courses ───────────────────────────────────────────
        $topCoursesByEnrollment = Course::with('teacher.user', 'category')
            ->withCount('enrollments')
            ->orderByDesc('enrollments_count')
            ->take(5)
            ->get();

        $topCoursesByRevenue = Course::with('teacher.user', 'category')
            ->select('courses.*')
            ->selectRaw('(price * (SELECT COUNT(*) FROM course_enrollments WHERE course_enrollments.course_id = courses.id)) as revenue')
            ->withCount('enrollments')
            ->orderByDesc('revenue')
            ->take(5)
            ->get();

        return response()->json([
            "message" => "Admin Dashboard Data Retrieved Successfully",
            "data" => [
                "totalUsers" => $totalUsers,
                "totalTeachers" => $totalTeachers,
                "totalStudents" => $totalStudents,
                "totalCourses" => $totalCourses,
                "recentUsers" => $recentUsers,
                "recentCourses" => $recentCourses,
                "userGrowth" => $userGrowthData,
                "courseEnrollments" => $enrollmentsData,
                "revenueTrend" => $revenueData,
                "topTeachersByRating" => $topTeachersByRating,
                "topTeachersBySessions" => $topTeachersBySessions,
                "topCoursesByEnrollment" => $topCoursesByEnrollment,
                "topCoursesByRevenue" => $topCoursesByRevenue,
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

    public function adminSubscriptions()
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

        $subscriptions = Subscription::with("user", "plan")->latest()->paginate(5);

        return response()->json([
            "message" => "Subscriptions Retrieved Successfully",
            "subscriptions" => $subscriptions
        ]);
    }
}
