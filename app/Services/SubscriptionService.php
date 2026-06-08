<?php

namespace App\Services;

use App\Models\LiveSession;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionService
{
    public function canCreateCourse(User $user): bool
    {
        $subscription = Subscription::with('plan')->where('user_id', $user->id)->first();

        if (!$subscription || $subscription->status !== "active") {
            return false;
        }

        $currentCourses = $user->teacher->publishedCourses()->count();
        $maxCourses = $subscription->plan->features['max_courses'];

        if ($maxCourses !== -1 && $currentCourses >= $maxCourses) {
            return false;
        }

        return true;
    }

    public function getLiveSessionsCreatedCount(User $user, ?Subscription $subscription = null): int
    {

        if (!$subscription) {
            $subscription = Subscription::with('plan')->where('user_id', $user->id)->where('status', 'active')->first();
        }

        $teacher = $user->teacher;
        if (!$teacher) {
            return 0;
        }

        return LiveSession::whereHas('booking', function ($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id);
        })
            ->whereBetween('created_at', [$subscription->start_at, $subscription->end_at])
            ->count();
    }

    public function canCreateLiveSession(User $user): bool
    {
        $subscription = Subscription::with('plan')->where("user_id", $user->id)->first();
        if (!$subscription || $subscription->status !== "active") {
            return false;
        }

        $currentCreatedSessions = $this->getLiveSessionsCreatedCount($user, $subscription);
        $maxSessions = $subscription->plan->features['sessions_per_month'];

        if ($maxSessions !== -1 && $currentCreatedSessions >= $maxSessions) {
            return false;
        }

        return true;
    }
}
