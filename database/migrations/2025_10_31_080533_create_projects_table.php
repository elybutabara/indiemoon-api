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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('language', 8)->index();
            $table->string('trim_size')->nullable(); // "132x205"
            $table->integer('page_count')->default(0);
            $table->decimal('paper_caliper_mm', 5, 3)->nullable();
            $table->string('genre')->nullable();
            $table->string('isbn')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
