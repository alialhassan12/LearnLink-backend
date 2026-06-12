<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\Teacher;
use App\Services\SubscriptionService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class bookingsController extends Controller
{
    public function newBooking(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'scheduled_date' => 'required|date',
            'scheduled_day' => 'required|string',
            'scheduled_time' => 'required|date_format:H:i',
            'subject' => 'required|string',
            'student_note' => 'nullable|string',
            'price' => 'required|decimal:2|min:0'
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized Access',
            ], 401);
        }

        $student = Student::where('user_id', $user->id)->first();
        if (!$student) {
            return response()->json([
                'message' => 'Unauthorized Access',
            ], 401);
        }

        $booking = Booking::create([
            'teacher_id' => $request->teacher_id,
            'student_id' => $student->id,
            'scheduled_day' => $request->scheduled_day,
            'scheduled_date' => $request->scheduled_date,
            'scheduled_time' => $request->scheduled_time,
            'subject' => $request->subject,
            'student_note' => $request->student_note,
            'price' => $request->price,
        ]);

        return response()->json([
            'message' => 'Booking created successfully',
            'booking' => $booking,
        ], 200);
    }

    public function getTeacherBookings(Request $request, SupabaseStorageService $storage, SubscriptionService $subscriptionService)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized Access',
            ], 401);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (!$teacher) {
            return response()->json([
                'message' => 'Unauthorized Access',
            ], 401);
        }

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        $max_live_sessions = $subscription ? ($subscription->plan->features['sessions_per_month'] ?? 0) : 0;
        $current_live_sessions = $subscriptionService->getLiveSessionsCreatedCount($user, $subscription);


        $bookings = Booking::with('student.user')->where('teacher_id', $teacher->id)->orderBy('scheduled_date', 'asc')->get();

        foreach ($bookings as $booking) {
            if ($booking->student->user->avatar) {
                $booking->student->user->avatar = $storage->getPublicUrl($booking->student->user->avatar);
            }
        }

        return response()->json([
            'message' => 'Bookings fetched successfully',
            'bookings' => $bookings,
            'max_live_sessions' => $max_live_sessions,
            'current_live_sessions' => $current_live_sessions
        ], 200);
    }

    public function getStudentBookings(Request $request, SupabaseStorageService $storage)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized Access',
            ], 401);
        }

        $student = Student::where('user_id', $user->id)->first();
        if (!$student) {
            return response()->json([
                'message' => 'Unauthorized Access',
            ], 401);
        }

        $bookings = Booking::with('teacher.user')->where('student_id', $student->id)->orderBy('scheduled_date', 'asc')->get();

        foreach ($bookings as $booking) {
            if ($booking->teacher->user->avatar) {
                $booking->teacher->user->avatar = $storage->getPublicUrl($booking->teacher->user->avatar);
            }
        }

        return response()->json([
            'message' => 'Bookings fetched successfully',
            'bookings' => $bookings,
        ], 200);
    }

    public function rejectBooking(Request $request, SupabaseStorageService $storage)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized Access',
            ], 401);
        }
        $teacher = $user->teacher;
        if (!$teacher) {
            return response()->json([
                'message' => 'Unauthorized Access',
            ], 401);
        }

        $booking = $teacher->bookings()->find($request->booking_id);
        if (!$booking) {
            return response()->json([
                'message' => 'Booking not found or unauthorized access',
            ], 401);
        }

        $booking->status = 'rejected';
        $booking->save();

        $booking->load('student.user');
        if ($booking->student->user->avatar) {
            $booking->student->user->avatar = $storage->getPublicUrl($booking->student->user->avatar);
        }

        return response()->json([
            'message' => 'Booking rejected successfully',
            'booking' => $booking,
        ], 200);
    }

    public function approveBooking(Request $request, SupabaseStorageService $storage, SubscriptionService $subscriptionService)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id'
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized Access',
            ], 401);
        }
        $teacher = $user->teacher;
        if (!$teacher) {
            return response()->json([
                'message' => 'Unauthorized Access',
            ], 401);
        }

        // load subscription and plan to avoid n+1 query issue
        $user->load('subscription.plan');
        $max_live_sessions = $user->subscription->plan->features['sessions_per_month'];
        $current_live_sessions = $subscriptionService->getLiveSessionsCreatedCount($user, $user->subscription);

        if ($current_live_sessions >= $max_live_sessions) {
            return response()->json([
                'message' => 'You have reached the maximum number of live sessions allowed per month. Please upgrade your subscription plan or wait for the next month.',
            ], 429);
        }

        $booking = $teacher->bookings()->with('student.user')->find($request->booking_id);
        if (!$booking) {
            return response()->json([
                'message' => 'Booking not found or unauthorized access',
            ], 401);
        }
        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'Booking is not pending',
            ], 400);
        }

        DB::transaction(function () use ($booking, $teacher) {
            LiveSession::create([
                'booking_id' => $booking->id,
                'scheduled_date' => $booking->scheduled_date,
                'scheduled_day' => $booking->scheduled_day,
                'scheduled_time' => $booking->scheduled_time,
                'subject' => $booking->subject,
                'student_note' => $booking->student_note,
            ]);

            $booking->status = 'approved';
            $booking->save();
        });

        // $booking->load('student.user');
        if ($booking->student->user->avatar) {
            $booking->student->user->avatar = $storage->getPublicUrl($booking->student->user->avatar);
        }

        $current_live_sessions++;

        return response()->json([
            'message' => 'Booking approved successfully',
            'booking' => $booking,
            'current_live_sessions' => $current_live_sessions,
        ], 200);
    }
}
