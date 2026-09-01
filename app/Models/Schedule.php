<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property int $class_room_id
 * @property int $subject_id
 * @property int|null $sub_subject_id
 * @property array|null $sub_subject_ids
 * @property int $teacher_id
 * @property int $session_id
 * @property string $day
 * @property string|null $schedule_date
 * @property int|null $week_number
 * @property int|null $month
 * @property int|null $year
 * @property string $status
 * @property string|null $reject_reason
 * @property string|null $reason
 */
class Schedule extends Model
{
    protected $fillable = [
        'class_room_id',
        'subject_id',
        'sub_subject_id',
        'sub_subject_ids',
        'teacher_id',
        'session_id',
        'day',
        'week_number',
        'schedule_date',
        'month',
        'year',
        'status',
        'reject_reason',
        'reason',
    ];

    protected $casts = [
        'sub_subject_ids' => 'array',
        'schedule_date' => 'date:Y-m-d',
    ];

    /**
     * Get the classroom for this schedule
     */
    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class);
    }

    /**
     * Get the subject for this schedule
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the sub-subject for this schedule
     */
    public function subSubject()
    {
        return $this->belongsTo(SubSubject::class);
    }

    public function getSubSubjectNamesAttribute()
    {
        $ids = is_array($this->sub_subject_ids) ? $this->sub_subject_ids : json_decode($this->sub_subject_ids ?? '[]', true);

        if (empty($ids)) {
            return $this->subSubject ? [$this->subSubject->name] : [];
        }

        return SubSubject::whereIn('id', $ids)->pluck('name')->toArray();
    }

    /**
     * Get the teacher for this schedule
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the session for this schedule
     */
    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    /**
     * Scope to filter schedules by day
     */
    public function scopeByDay(Builder $query, $day)
    {
        return $query->where('day', $day);
    }

    /**
     * Scope to filter schedules by teacher
     */
    public function scopeByTeacher(Builder $query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    /**
     * Scope to filter schedules by classroom
     */
    public function scopeByClass(Builder $query, $classId)
    {
        return $query->where('class_room_id', $classId);
    }

    /**
     * Check if a classroom has a schedule conflict
     */
    public static function hasClassConflict($classId, $day, $sessionId, $ignoreId = null)
    {
        $query = self::query()
            ->where('class_room_id', $classId)
            ->where('day', $day)
            ->where('session_id', $sessionId);

        if (!is_null($ignoreId)) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * Check if a teacher has a schedule conflict
     */
    public static function hasTeacherConflict($teacherId, $day, $sessionId, $ignoreId = null)
    {
        $query = self::query()
            ->where('teacher_id', $teacherId)
            ->where('day', $day)
            ->where('session_id', $sessionId);

        if (!is_null($ignoreId)) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
