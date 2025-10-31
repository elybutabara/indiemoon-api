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
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['pdf_innmat','pdf_omslag','epub','idml','package']);
            $table->string('path')->nullable();    // s3 path
            $table->string('checksum')->nullable();
            $table->json('report')->nullable();    // preflight/epubcheck output
            $table->enum('status', ['queued','processing','done','failed'])->default('queued');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
