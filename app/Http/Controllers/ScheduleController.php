<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\SubSubject;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Exception;

class ScheduleController extends Controller
{
    /**
     * Get all schedules with optional filtering by day, teacher, or class
     */
    public function index(Request $request)
    {
        try {
            $query = Schedule::with([
                'classRoom',
                'teacher.user',
                'subject',
                'subSubject',
                'session',
            ]);

            if ($request->day) {
                $query->where('day', $request->day);
            }

            if ($request->teacher_id) {
                $query->where('teacher_id', $request->teacher_id);
            }

            if ($request->class_room_id) {
                $query->where('class_room_id', $request->class_room_id);
            }

            if ($request->week_number) {
                $query->where('week_number', $request->week_number);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if (Schema::hasColumn('schedules', 'schedule_date')) {
                $query->orderBy('schedule_date');
            }

            $query->orderBy('day')->orderBy('session_id');

            return response()->json(
                $query->get()
            );
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve schedules',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new schedule with conflict validation
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'class_room_id' => 'required|exists:class_rooms,id',
                'subject_id' => 'required|exists:subjects,id',
                'teacher_id' => 'required|exists:teachers,id',
                'sub_subject_id' => 'nullable|exists:sub_subjects,id',
                'sub_subject_ids' => 'nullable|array',
                'sub_subject_ids.*' => 'integer|exists:sub_subjects,id',
                'session_id' => 'required|exists:sessions,id',
                'day' => [
                    'required',
                    Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                ],
                'schedule_date' => 'nullable|date',
                'week_number' => 'nullable|integer|min:1|max:4',
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2024',
                'status' => 'nullable|string|max:50',
                'reject_reason' => 'nullable|string|max:500',
            ]);

            $teacher = Teacher::findOrFail($validated['teacher_id']);

            if ($teacher->subject_id != $validated['subject_id']) {
                return response()->json([
                    'message' => 'Validation failed',
                    'error' => 'Teacher does not teach the selected subject.',
                ], 422);
            }

            $selectedSubSubjectIds = array_values(array_unique(array_filter(
                $validated['sub_subject_ids'] ?? ($validated['sub_subject_id'] ? [$validated['sub_subject_id']] : []),
                fn ($id) => !empty($id)
            )));

            foreach ($selectedSubSubjectIds as $subSubjectId) {
                $validSub = SubSubject::where('id', $subSubjectId)
                    ->where('subject_id', $validated['subject_id'])
                    ->exists();

                if (!$validSub) {
                    return response()->json([
                        'message' => 'Validation failed',
                        'error' => 'Sub-subject does not belong to the selected subject.',
                    ], 422);
                }
            }

            $scheduleDate = !empty($validated['schedule_date']) ? $validated['schedule_date'] : now()->toDateString();
            $weekNumber = $validated['week_number'] ?? min(4, max(1, (int) ceil((int) date('d', strtotime($scheduleDate)) / 7)));
            $month = $validated['month'] ?? (int) date('n', strtotime($scheduleDate));
            $year = $validated['year'] ?? (int) date('Y', strtotime($scheduleDate));

            $data = [
                'class_room_id' => $validated['class_room_id'],
                'teacher_id' => $teacher->id,
                'subject_id' => $teacher->subject_id,
                'sub_subject_id' => $selectedSubSubjectIds[0] ?? null,
                'sub_subject_ids' => $selectedSubSubjectIds,
                'session_id' => $validated['session_id'],
                'day' => $validated['day'],
                'schedule_date' => $scheduleDate,
                'week_number' => $weekNumber,
                'month' => $month,
                'year' => $year,
                'status' => $validated['status'] ?? 'active',
                'reject_reason' => $validated['reject_reason'] ?? null,
            ];

            if (Schedule::hasClassConflict($data['class_room_id'], $data['day'], $data['session_id'])) {
                return response()->json([
                    'message' => 'Conflict detected',
                    'error' => 'This classroom already has a schedule in this session.',
                ], 409);
            }

            if (Schedule::hasTeacherConflict($data['teacher_id'], $data['day'], $data['session_id'])) {
                return response()->json([
                    'message' => 'Conflict detected',
                    'error' => 'This teacher already has a schedule in this session.',
                ], 409);
            }

            $schedule = Schedule::create($data);

            return response()->json(
                [
                    'message' => 'Schedule created successfully',
                    'data' => $schedule->load([
                        'classRoom',
                        'teacher.user',
                        'subject',
                        'subSubject',
                        'session',
                    ]),
                ],
                201
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred',
                'error' => 'Failed to create schedule. Please try again.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing schedule with conflict validation
     */
    public function update(Request $request, Schedule $schedule)
    {
        try {
            $validated = $request->validate([
                'class_room_id' => 'required|exists:class_rooms,id',
                'subject_id' => 'required|exists:subjects,id',
                'teacher_id' => 'required|exists:teachers,id',
                'sub_subject_id' => 'nullable|exists:sub_subjects,id',
                'sub_subject_ids' => 'nullable|array',
                'sub_subject_ids.*' => 'integer|exists:sub_subjects,id',
                'session_id' => 'required|exists:sessions,id',
                'day' => [
                    'required',
                    Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                ],
                'schedule_date' => 'nullable|date',
                'week_number' => 'nullable|integer|min:1|max:4',
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2024',
                'status' => 'nullable|string|max:50',
                'reject_reason' => 'nullable|string|max:500',
            ]);

            $teacher = Teacher::findOrFail($validated['teacher_id']);

            if ($teacher->subject_id != $validated['subject_id']) {
                return response()->json([
                    'message' => 'Validation failed',
                    'error' => 'Teacher does not teach the selected subject.',
                ], 422);
            }

            $selectedSubSubjectIds = array_values(array_unique(array_filter(
                $validated['sub_subject_ids'] ?? ($validated['sub_subject_id'] ? [$validated['sub_subject_id']] : []),
                fn ($id) => !empty($id)
            )));

            foreach ($selectedSubSubjectIds as $subSubjectId) {
                $validSub = SubSubject::where('id', $subSubjectId)
                    ->where('subject_id', $validated['subject_id'])
                    ->exists();

                if (!$validSub) {
                    return response()->json([
                        'message' => 'Validation failed',
                        'error' => 'Sub-subject does not belong to the selected subject.',
                    ], 422);
                }
            }

            $scheduleDate = !empty($validated['schedule_date']) ? $validated['schedule_date'] : $schedule->schedule_date ?? now()->toDateString();
            $weekNumber = $validated['week_number'] ?? $schedule->week_number ?? min(4, max(1, (int) ceil((int) date('d', strtotime($scheduleDate)) / 7)));
            $month = $validated['month'] ?? $schedule->month ?? (int) date('n', strtotime($scheduleDate));
            $year = $validated['year'] ?? $schedule->year ?? (int) date('Y', strtotime($scheduleDate));
            $scheduleId = (int) $schedule->getKey();

            $data = [
                'class_room_id' => $validated['class_room_id'],
                'teacher_id' => $teacher->id,
                'subject_id' => $teacher->subject_id,
                'sub_subject_id' => $selectedSubSubjectIds[0] ?? null,
                'sub_subject_ids' => $selectedSubSubjectIds,
                'session_id' => $validated['session_id'],
                'day' => $validated['day'],
                'schedule_date' => $scheduleDate,
                'week_number' => $weekNumber,
                'month' => $month,
                'year' => $year,
                'status' => $validated['status'] ?? $schedule->status ?? 'active',
                'reject_reason' => $validated['reject_reason'] ?? $schedule->reject_reason ?? null,
            ];

            if (Schedule::hasClassConflict($data['class_room_id'], $data['day'], $data['session_id'], $scheduleId)) {
                return response()->json([
                    'message' => 'Conflict detected',
                    'error' => 'This classroom already has a schedule in this session.',
                ], 409);
            }

            if (Schedule::hasTeacherConflict($data['teacher_id'], $data['day'], $data['session_id'], $scheduleId)) {
                return response()->json([
                    'message' => 'Conflict detected',
                    'error' => 'This teacher already has a schedule in this session.',
                ], 409);
            }

            $schedule->update($data);

            return response()->json([
                'message' => 'Schedule updated successfully',
                'data' => $schedule->load([
                    'classRoom',
                    'teacher.user',
                    'subject',
                    'subSubject',
                    'session',
                ]),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred',
                'error' => 'Failed to update schedule. Please try again.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a schedule
     */
    public function destroy(Schedule $schedule)
    {
        try {
            $schedule->delete();

            return response()->json([
                'message' => 'Schedule deleted successfully',
            ]);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete schedule',
                'error' => 'Failed to delete schedule. Please try again.',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to delete schedule',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get teachers who teach a specific subject
     */
    public function teachersBySubject($subjectId)
    {
        try {
            return response()->json([
                'data' => Teacher::with('user')
                    ->where('subject_id', $subjectId)
                    ->get(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve teachers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sub-subjects of a specific subject
     */
    public function subSubjectsBySubject($subjectId)
    {
        try {
            return response()->json([
                'data' => SubSubject::where('subject_id', $subjectId)->get(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve sub-subjects',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get schedules for a specific class
     */
    public function byClass($classRoomId)
    {
        try {
            return response()->json([
                'data' => Schedule::with([
                    'classRoom',
                    'teacher.user',
                    'subject',
                    'subSubject',
                    'session',
                ])
                    ->where('class_room_id', $classRoomId)
                    ->orderBy('day')
                    ->orderBy('session_id')
                    ->get(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve schedules',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get schedules for a specific teacher
     */
    public function byTeacher($teacherId)
    {
        try {
            return response()->json([
                'data' => Schedule::with([
                    'classRoom',
                    'teacher.user',
                    'subject',
                    'subSubject',
                    'session',
                ])
                    ->where('teacher_id', $teacherId)
                    ->orderBy('day')
                    ->orderBy('session_id')
                    ->get(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve schedules',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get authenticated teacher's own schedule
     */
    public function mySchedule(Request $request)
    {
        $teacher = $request->user()->teacher;

        if (!$teacher) {
            return response()->json([
                'message' => 'Teacher not found',
            ], 404);
        }

        $query = Schedule::with([
            'classRoom',
            'subject',
            'subSubject',
            'session',
            'teacher.user',
        ])
            ->where('teacher_id', $teacher->id)
            ->where('status', '!=', 'rejected')
            ->orderBy('schedule_date')
            ->orderBy('day')
            ->orderBy('session_id');

        return response()->json($query->get());
    }

    public function rejectSchedule(Request $request, Schedule $schedule)
    {
        $teacher = $request->user()->teacher;

        if (!$teacher || $schedule->teacher_id !== $teacher->id) {
            return response()->json([
                'message' => 'Forbidden',
                'error' => 'Anda tidak dapat menolak jadwal ini.',
            ], 403);
        }

        $validated = $request->validate([
            'reject_reason' => 'nullable|string|max:500',
        ]);

        $schedule->update([
            'status' => 'rejected',
            'reject_reason' => $validated['reject_reason'] ?? 'Jadwal ditolak oleh guru.',
        ]);

        return response()->json([
            'message' => 'Jadwal berhasil ditolak.',
            'data' => $schedule->fresh(),
        ]);
    }

    public function reassignTeacher(Request $request, Schedule $schedule)
    {
        if (!$request->user() || !$request->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Forbidden',
                'error' => 'Hanya admin yang dapat mengganti pengajar.',
            ], 403);
        }

        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $newTeacher = Teacher::findOrFail($validated['teacher_id']);

        if ($newTeacher->subject_id != $schedule->subject_id) {
            return response()->json([
                'message' => 'Validation failed',
                'error' => 'Pengajar baru harus mengajar mapel yang sama.',
            ], 422);
        }

        $schedule->update([
            'teacher_id' => $newTeacher->id,
            'status' => 'active',
            'reject_reason' => null,
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'Pengajar berhasil diganti.',
            'data' => $schedule->fresh()->load(['teacher.user', 'subject']),
        ]);
    }

    public function confirmRejection(Schedule $schedule)
    {
        if (!$schedule->reject_reason) {
            return response()->json([
                'message' => 'Jadwal ini belum ditolak oleh guru.',
            ], 422);
        }

        $schedule->update([
            'status' => 'rejected',
        ]);

        return response()->json([
            'message' => 'Penolakan guru berhasil dikonfirmasi.',
            'data' => $schedule->fresh()->load(['teacher.user', 'subject', 'classRoom', 'session']),
        ]);
    }

    /**
     * Get authenticated student's class schedule
     */
    public function classSchedule(Request $request)
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json([
                'message' => 'Student not found',
            ], 404);
        }

        $classIds = $student->classRooms()->pluck('class_rooms.id');

        return response()->json(
            Schedule::with([
                'teacher.user',
                'subject',
                'subSubject',
                'session',
                'classRoom',
            ])
                ->whereIn('class_room_id', $classIds)
                ->orderBy('day')
                ->orderBy('session_id')
                ->get()
        );
    }
}
