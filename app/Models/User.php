<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'student_id',
        'department',
        'gender',
        'status',
        'assigned_block',
        'year_level',
        'date_of_birth',
        'address',
        'emergency_contact',
        'emergency_phone'
    ];// we use this to allow mass assignment on these fields

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'date_of_birth' => 'date',
    ];

    // User types
    const TYPE_STUDENT = 'student';
    const TYPE_SUPERVISOR = 'supervisor';
    const TYPE_ADMIN = 'admin';

    // User statuses
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_REJECTED = 'rejected';

    // JWT methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // Relationships
    public function dormitoryApplications()
    {
        return $this->hasMany(DormitoryApplication::class, 'student_id');
    }

    public function roomAssignment()
    {
        return $this->hasOne(RoomAssignment::class, 'student_id');
    }

    public function roomAssignments()
    {
        return $this->hasMany(RoomAssignment::class, 'student_id');
    }

    public function changeRequests()
    {
        return $this->hasMany(ChangeRequest::class, 'student_id');
    }

    public function temporaryLeaveRequests()
    {
        return $this->hasMany(TemporaryLeaveRequest::class, 'student_id');
    }

    public function supervisedLeaveRequests()
    {
        return $this->hasMany(TemporaryLeaveRequest::class, 'supervisor_id');
    }

    // Scopes
    public function scopeStudents($query)
    {
        return $query->where('user_type', self::TYPE_STUDENT);
    }

    public function scopeSupervisors($query)
    {
        return $query->where('user_type', self::TYPE_SUPERVISOR);
    }

    public function scopeAdmins($query)
    {
        return $query->where('user_type', self::TYPE_ADMIN);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // Helper methods
    public function isStudent()
    {
        return $this->user_type === self::TYPE_STUDENT;
    }

    public function isSupervisor()
    {
        return $this->user_type === self::TYPE_SUPERVISOR;
    }

    public function isAdmin()
    {
        return $this->user_type === self::TYPE_ADMIN;
    }

    public function hasActiveApplication()
    {
        return $this->dormitoryApplications()
            ->whereIn('status', [
                DormitoryApplication::STATUS_PENDING,
                DormitoryApplication::STATUS_APPROVED
            ])
            ->exists();
    }

    public function hasRoomAssignment()
    {
        return $this->roomAssignment()->exists();
    }

    // Age calculation and validation
    public function getAgeAttribute()
    {
        if (!$this->date_of_birth) {
            return null;
        }
        
        return \Carbon\Carbon::parse($this->date_of_birth)->age;
    }

    public function isAgeRealistic()
    {
        $age = $this->age;
        
        if ($age === null) {
            return true; // No age provided, skip validation
        }
        
        return $age >= 16 && $age <= 70;
    }

    public function getAgeWarningAttribute()
    {
        $age = $this->age;
        
        if ($age === null) {
            return null;
        }
        
        if ($age < 16) {
            return 'Age seems too young for university admission (under 16)';
        }
        
        if ($age > 70) {
            return 'Age seems unusually high for a student (over 70)';
        }
        
        if ($age < 18) {
            return 'Student is under 18 - may require special permissions';
        }
        
        return null;
    }
}