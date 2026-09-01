<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Models\SubSubject;

class TeacherDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:teacher']);
    }

    /**
     * Get teacher dashboard with today's schedules
     */
    public function index()
    {
        $teacher = Auth::user()->teacher()->with(['user', 'subject'])->first();

        if (!$teacher) {
            return response()->json([
                'message' => 'Teacher data not found',
            ], 404);
        }

        $today = strtolower(Carbon::now('Asia/Jakarta')->format('l'));

        $teacherSchedulesQuery = Schedule::with([
            'classRoom:id,name',
            'subject:id,name',
            'subSubject:id,name',
            'session:id,name,start_time,end_time',
        ])->where('teacher_id', $teacher->id);

        if (Schema::hasColumn('schedules', 'schedule_date')) {
            $teacherSchedulesQuery->orderBy('schedule_date');
        }

        $teacherSchedules = $teacherSchedulesQuery
            ->orderBy('session_id')
            ->get();

        $subSubjectIds = $teacherSchedules
            ->flatMap(function ($schedule) {
                $ids = is_array($schedule->sub_subject_ids)
                    ? $schedule->sub_subject_ids
                    : [];

                return array_merge($ids, $schedule->sub_subject_id ? [$schedule->sub_subject_id] : []);
            })
            ->unique()
            ->values();

        $subSubjects = SubSubject::whereIn('id', $subSubjectIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $teacherSchedules->each(function ($schedule) use ($subSubjects) {
            $ids = is_array($schedule->sub_subject_ids)
                ? $schedule->sub_subject_ids
                : [];
            $ids = array_merge($ids, $schedule->sub_subject_id ? [$schedule->sub_subject_id] : []);

            $schedule->setRelation(
                'subSubjects',
                collect($ids)->unique()->map(fn ($id) => $subSubjects->get($id))->filter()->values()
            );
        });

        $todaySchedules = $teacherSchedules->filter(function ($schedule) use ($today) {
            return strtolower($schedule->day ?? '') === $today;
        })->values();

        return response()->json([
            'teacher' => $teacher,
            'subject' => $teacher->subject,
            'today' => $today,
            'today_schedules' => $todaySchedules,
            'all_schedules' => $teacherSchedules,
            'total_schedules' => $teacherSchedules->count(),
            'total_classes' => $teacherSchedules->unique('class_room_id')->count(),
        ]);
    }
}
