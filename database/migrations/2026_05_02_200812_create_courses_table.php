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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId("teacher_id")->constrained("teachers")->cascadeOnDelete();
            $table->foreignId("category_id")->constrained("categories")->cascadeOnDelete();
            $table->string("title");
            $table->text("description");
            $table->string("thumbnail");
            $table->string("language");
            $table->enum("status",["published","archived","draft"] )->default("draft");
            $table->decimal("price", 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
