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
        Schema::table('schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('schedules', 'schedule_date')) {
                $table->date('schedule_date')->nullable()->after('day');
            }

            if (!Schema::hasColumn('schedules', 'week_number')) {
                $table->unsignedTinyInteger('week_number')->nullable()->after('schedule_date');
            }

            if (!Schema::hasColumn('schedules', 'month')) {
                $table->unsignedTinyInteger('month')->nullable()->after('week_number');
            }

            if (!Schema::hasColumn('schedules', 'year')) {
                $table->year('year')->nullable()->after('month');
            }

            if (!Schema::hasColumn('schedules', 'sub_subject_ids')) {
                $table->json('sub_subject_ids')->nullable()->after('year');
            }

            if (!Schema::hasColumn('schedules', 'status')) {
                $table->string('status')->default('active')->after('sub_subject_ids');
            }

            if (!Schema::hasColumn('schedules', 'reject_reason')) {
                $table->text('reject_reason')->nullable()->after('status');
            }

            if (!Schema::hasColumn('schedules', 'reason')) {
                $table->text('reason')->nullable()->after('reject_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $columns = ['reason', 'reject_reason', 'status', 'sub_subject_ids', 'year', 'month', 'week_number', 'schedule_date'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('schedules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
