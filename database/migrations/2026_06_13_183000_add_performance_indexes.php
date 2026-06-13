<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clean up duplicate course enrollments if any exist
        DB::table('course_enrollments')
            ->whereIn('id', function ($query) {
                $query->select('id')
                    ->from('course_enrollments')
                    ->whereRaw('id < (SELECT MAX(id) FROM course_enrollments AS temp WHERE temp.student_id = course_enrollments.student_id AND temp.course_id = course_enrollments.course_id)');
            })
            ->delete();

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('plan_id');
            $table->index('status');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->unique(['student_id', 'course_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::table('ai_messages', function (Blueprint $table) {
            $table->index('ai_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['plan_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'course_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'created_at']);
        });

        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropIndex(['ai_chat_id']);
        });
    }
};
