<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassRoom extends Model
{
    protected $fillable = [
        'name',
        'subject_id',
        'teacher_id',
    ];

    /**
     * Get all students enrolled in this classroom
     */
    public function students()
    {
        return $this->belongsToMany(Student::class, 'class_student')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * Get all schedules for this classroom
     */
    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
