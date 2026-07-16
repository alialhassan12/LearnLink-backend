<?php

use App\Http\Controllers\Api\adminController;
use App\Http\Controllers\Api\aiChatController;
use App\Http\Controllers\Api\aiMessages;
use App\Http\Controllers\Api\aiMessagesController;
use App\Http\Controllers\Api\aiRequestController;
use App\Http\Controllers\Api\authController;
use App\Http\Controllers\Api\bookingsController;
use App\Http\Controllers\Api\calendarController;
use App\Http\Controllers\Api\categoriesController;
use App\Http\Controllers\Api\conversationsController;
use App\Http\Controllers\Api\courseEnrollmentController;
use App\Http\Controllers\Api\courseReviewController;
use App\Http\Controllers\Api\coursesController;
use App\Http\Controllers\Api\liveSessionsController;
use App\Http\Controllers\Api\messageController;
use App\Http\Controllers\Api\notificationController;
use App\Http\Controllers\Api\plansController;
use App\Http\Controllers\Api\pushTokenController;
use App\Http\Controllers\Api\sessionMaterialsController;
use App\Http\Controllers\Api\sessionReviewsController;
use App\Http\Controllers\Api\studentController;
use App\Http\Controllers\Api\subscriptionsController;
use App\Http\Controllers\Api\teacherController;
use App\Models\CourseReview;
use App\Models\LiveSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

// public routes
Route::post('/auth/register',[authController::class,'register'])
    ->middleware('throttle:auth')
    ->name('register_new_user');
Route::post('/auth/login',[authController::class,'login'])
    ->middleware('throttle:auth')
    ->name('login_user');

// auth routes
Route::middleware(['auth:sanctum','throttle:api'])->group(function () {
    // common routes between roles
    Route::post('/auth/logout',[authController::class,'logout'])->name('logout_user');
    Route::get('/auth/me',[authController::class,'checkAuth'])->name('check_auth');
    
    Route::get('/categories',[categoriesController::class,'getCategories'])->name('get_categories');
    
    Route::get('/courses/course/{id}',[coursesController::class,'getCourseWithMaterialsById'])->name('get-course-with-materials-by-id');
    
    Route::post('/livekit/token',[liveSessionsController::class,'getToken'])->name('get_livekit_token');
    
    Route::post('/messages/send',[messageController::class,'send'])->name('send-message');
    Route::get('/messages/conversations',[conversationsController::class,'getConversations'])->name('get-conversations');
    Route::post('/messages/conversation',[messageController::class,'getMessagesByConversation'])->name('get-messages');
    
    Route::post('/ai/messages',[aiMessagesController::class,'getMessages'])->name('get-ai-messages');
    Route::get('/ai/chats',[aiChatController::class,'getChats'])->name('get-user-ai-chats');
    Route::post('/ai/messages/new',[aiMessagesController::class,'sendMessage'])->name('send-ai-message');
    Route::post('/ai/messages-with-file/new',[aiMessagesController::class,'sendMessageWithFile'])->name('send-ai-message-with-file');
    
    Route::get('/plans',[plansController::class,'getAllActivePlans'])->name('get-all-active-plans');
    Route::post('/plans/subscription/upgrade',[subscriptionsController::class,'upgradeSubscription'])->name('upgrade-subscription');

    Route::post('/notifications/push-token',[pushTokenController::class,'store'])->name('store-push-token');
    Route::get('/notifications/history',[notificationController::class,'getNotificationHistory'])->name('get-notification-history');

    // admin routes
    Route::middleware(['checkRole:admin'])->group(function(){
        Route::get('/admin/dashboard',[adminController::class,'adminDashboard'])->name('admin-dashboard');
        Route::get('/admin/users',[adminController::class,'getUsers'])->name('get-users');
        Route::put('/admin/users/suspend',[adminController::class,'suspendUser'])->name('suspend-user');
        Route::put('/admin/users/activate',[adminController::class,'activateUser'])->name('activate-user');
        Route::get('/admin/plans',[plansController::class,'getAllPlans'])->name('get-all-plans');
        Route::post('/admin/plans/create-plan',[plansController::class,'createPlan'])->name('create-new-plan');
        Route::put('/admin/plans/change-status',[plansController::class,'changePlanStatus'])->name('change-plan-status');
        Route::put('/admin/plans/update',[plansController::class,'updatePlan'])->name('update-plan');
        Route::get("/admin/categories",[categoriesController::class,'listCategoriesToAdmin'])->name("list-admin-categories");
        Route::post("/admin/categories/new",[categoriesController::class,'createCategory'])->name("create-category");
        Route::put("/admin/categories/update",[categoriesController::class,'updateCategory'])->name("update-category");
        Route::delete("/admin/categories/delete/{id}",[categoriesController::class,'deleteCategory'])->name("delete-category");
        Route::put("/admin/categories/change-status",[categoriesController::class,'changeCategoryStatus'])->name("change-category-status");
        Route::get("/admin/courses",[adminController::class,"adminGetCourses"])->name("admin-get-courses");
        Route::get("/admin/courses/{id}",[adminController::class,"adminGetCourseDetails"])->name("admin-get-course-details");
        Route::put("/admin/courses/change-status",[adminController::class,"adminChangeCourseStatus"])->name("admin-change-course-status");
        Route::get("/admin/subscriptions",[adminController::class,"adminSubscriptions"])->name("admin-get-subscriptions");
    });

    // teacher routes
    Route::middleware(['checkRole:teacher'])->group(function(){
        Route::get('/teacher/dashboard',[teacherController::class,'teacherDashboard'])->name('teacher-dashboard');
        Route::get('/teacher/profile',[teacherController::class,'teacherProfile'])->name('teacher_profile');
        Route::put('/teacher/update-profile',[teacherController::class,'teacherUpdate'])->name('teacher_update');
        Route::post('/courses/create-course',[coursesController::class,'createCourse'])->name('create_course');
        Route::post('/courses/save-draft',[coursesController::class,'saveDraftCourse'])->name('save-draft-course');
        Route::put('/courses/edit-course',[coursesController::class,'editCourse'])->name('edit-course');
        Route::get('/courses/my-courses',[coursesController::class,'getTeacherCourses'])->name('get_teacher_courses');
        Route::post('/courses/change-course-status',[coursesController::class,'changeCourseStatus'])->name('change-course-status');
        Route::get('/bookings/teacher-bookings',[bookingsController::class,'getTeacherBookings'])->name('get-teacher-bookings');
        Route::post('/bookings/reject-booking',[bookingsController::class,'rejectBooking'])->name('reject-booking');
        Route::post('/bookings/approve-booking',[bookingsController::class,'approveBooking'])->name('approve-booking');
        Route::get('/calendar/get-events',[calendarController::class,'getTeacherCalendarEvents'])->name('get-teacher-calendar-events');
        Route::post('/live-session/end-session',[liveSessionsController::class,'endSession'])->name('teacher-end-session');
        Route::get('/live-sessions/teacher-sessions',[liveSessionsController::class,'getTeacherLiveSessions'])->name('get-teacher-sessions');
        Route::get('/live-sessions/teacher-session/{id}',[liveSessionsController::class,'getTeacherSessionById'])->name('get-teacher-session-by-id');
        Route::post('/live-sessions/upload-materials',[sessionMaterialsController::class,'uploadSessionMaterials'])->name('upload-session-materials');
        Route::delete('/live-sessions/delete-material',[sessionMaterialsController::class,'deleteSessionMaterial'])->name('delte-session-material');
    });

    // student routes
    Route::middleware(['checkRole:student'])->group(function(){
        Route::get('/teachers',[teacherController::class,'getTeachers'])->name('get_teachers');
        Route::post('/teachers/filters',[teacherController::class,'getTeachersByFilters'])->name('get_teachers_by_filters');
        Route::get('/teachers/subjects',[teacherController::class,'getSubjects'])->name('get_subjects');
        Route::get('/teachers/languages',[teacherController::class,'getLanguages'])->name('get_languages');
        Route::get('/teacher/{id}',[teacherController::class,'getTeacherById'])->name('get_teacher_by_id');
        Route::post('/booking/new-booking',[bookingsController::class,'newBooking'])->name('new-booking');
        Route::get('/bookings/student-bookings',[bookingsController::class,'getStudentBookings'])->name('get-student-bookings');
        Route::get('/live-sessions/student-sessions',[liveSessionsController::class,'getStudentliveSessions'])->name('get-student-live-sessions');
        Route::get('/live-sessions/student-session/{id}',[liveSessionsController::class,'getStudentSessionById'])->name('get-student-session-by-id');
        Route::post('/live-sessions/review/new',[sessionReviewsController::class,'createSessionReview'])->name('create-session-review');
        Route::get('/courses/get-courses',[coursesController::class,'getCourses'])->name('get_courses');
        Route::post('/courses/get-courses/filtered',[coursesController::class,'getCoursesByFilters'])->name('get_courses_by_filters');
        Route::get('/courses/get-course/{id}',[coursesController::class,'getCourseById'])->name('get-course-by-id');
        Route::post('/courses/enroll',[courseEnrollmentController::class,'createEnrollment'])->name('create-enrollment');
        Route::get('/courses/enrolled-courses-ids',[courseEnrollmentController::class,'getEnrolledCoursesIds'])->name('get-enrolled-courses-ids');
        Route::get('/courses/enrolled-courses',[courseEnrollmentController::class,'getEnrolledCourses'])->name('get-enrolled-courses');
        Route::get('/courses/download-material/{id}',[coursesController::class,'downloadCourseMaterial'])->name('download-course-material');
        Route::post('/courses/review/new',[courseReviewController::class,'createCourseReview'])->name('create-course-review');
        Route::get('/student/profile',[studentController::class,'getStudent'])->name('get-student-profile');
        Route::put('/student/update-profile',[studentController::class,'editStudentProfile'])->name('edit-student-profile');
        Route::get('/student/get-student-id',[studentController::class,'getLoggedInStudentId'])->name('get-student-id');
    });
});
