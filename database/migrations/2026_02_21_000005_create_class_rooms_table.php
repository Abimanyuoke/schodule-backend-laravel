<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_rooms', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->foreignId('subject_id')
                  ->nullable()
                  ->constrained('subjects')
                  ->nullOnDelete();

            $table->foreignId('teacher_id')
                  ->nullable()
                  ->constrained('teachers')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_rooms');
    }
};
