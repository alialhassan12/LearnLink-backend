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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->integer("user_id")->constrained("users")->cascadeOnDelete()->cascadeOnUpdate();
            $table->integer('plan_id')->constrained("plans")->cascadeOnDelete()->cascadeOnUpdate();
            $table->integer('tokens_used')->default(0);
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->enum('status', ['active', 'expired', 'canceled'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
