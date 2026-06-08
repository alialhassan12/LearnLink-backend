<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Get current check constraint name for role column
            $constraint = DB::selectOne("
                SELECT conname
                FROM pg_constraint
                WHERE conrelid = 'users'::regclass
                AND pg_get_constraintdef(oid) LIKE '%role%'
            ");

            if ($constraint) {
                DB::statement("
                    ALTER TABLE users
                    DROP CONSTRAINT {$constraint->conname}
                ");
            }

            DB::statement("
                ALTER TABLE users
                ADD CONSTRAINT users_role_check
                CHECK (role IN ('student', 'teacher', 'admin'))
            ");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            DB::statement("
                ALTER TABLE users
                DROP CONSTRAINT users_role_check
            ");

            DB::statement("
                ALTER TABLE users
                ADD CONSTRAINT users_role_check
                CHECK (role IN ('student', 'teacher'))
            ");
        });
    }
};
