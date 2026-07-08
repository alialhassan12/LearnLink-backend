<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions',function(Blueprint $table){
            $table->unsignedBigInteger('user_id')->change();
            $table->unsignedBigInteger('plan_id')->change();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['plan_id']);

            $table->integer('user_id')->change();
            $table->integer('plan_id')->change();
        });
    }
};
