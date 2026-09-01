<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'kode_user',
        'name',
        'password',
        'role',
        'profile_photo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Use kode_user as the unique identifier for authentication
     */
    public function getAuthIdentifierName()
    {
        return 'kode_user';
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Get associated student record
     */
    public function student()
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Get associated teacher record
     */
    public function teacher()
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * Get profile photo URL from storage
     */
    public function getProfilePhotoUrlAttribute()
    {
        if ($this->profile_photo) {
            return Storage::url($this->profile_photo);
        }

        return asset('images/default-avatar.png');
    }
}
