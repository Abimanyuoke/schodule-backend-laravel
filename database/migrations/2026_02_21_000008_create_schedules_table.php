<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();

            // 1️⃣ Class
            $table->foreignId('class_room_id')
                ->constrained('class_rooms')
                ->cascadeOnDelete();

            // 2️⃣ Subject
            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete();

            // 3️⃣ Sub Subject
            $table->foreignId('sub_subject_id')
                ->nullable()
                ->constrained('sub_subjects')
                ->nullOnDelete();

            // 4️⃣ Teacher
            $table->foreignId('teacher_id')
                ->constrained('teachers')
                ->cascadeOnDelete();

            // 5️⃣ Session
            $table->foreignId('session_id')
                ->constrained('sessions')
                ->cascadeOnDelete();

            // 6️⃣ Day
            $table->enum('day', [
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
                'saturday',
                'sunday'
            ]);

            $table->date('schedule_date')->nullable();
            $table->unsignedTinyInteger('week_number')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->year('year')->nullable();
            $table->json('sub_subject_ids')->nullable();
            $table->string('status')->default('active');
            $table->text('reject_reason')->nullable();
            $table->text('reason')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | UNIQUE CONSTRAINTS (ANTI BENTROK)
            |--------------------------------------------------------------------------
            */

            // 🔥 Tidak boleh 1 kelas punya 2 mapel di sesi & hari yang sama
            $table->unique(
                ['class_room_id', 'session_id', 'day'],
                'class_conflict_unique'
            );

            // 🔥 Tidak boleh 1 guru mengajar 2 kelas di sesi & hari yang sama
            $table->unique(
                ['teacher_id', 'session_id', 'day'],
                'teacher_conflict_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
