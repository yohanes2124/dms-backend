<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DormitoryApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'preferred_room_id',
        'preferred_block',
        'room_type_preference',
        'application_date',
        'status',
        'priority_score',
        'special_requirements',
        'emergency_contact_name',
        'emergency_contact_phone',
        'medical_conditions',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'notes'
    ];

    protected $casts = [
        'application_date' => 'date',
        'approved_at' => 'datetime',
        'special_requirements' => 'array',
        'priority_score' => 'integer'
    ];

    // Application statuses
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Relationships
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function preferredRoom()
    {
        return $this->belongsTo(Room::class, 'preferred_room_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function roomAssignment()
    {
        return $this->hasOne(RoomAssignment::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeByBlock($query, $block)
    {
        return $query->where('preferred_block', $block);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority_score', 'desc')
                    ->orderBy('application_date', 'asc');
    }

    // Helper methods
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected()
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function canBeApproved()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_SUBMITTED]);
    }

    public function canBeRejected()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_SUBMITTED]);
    }

    public function approve($approverId, $notes = null)
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approverId,
            'approved_at' => now(),
            'notes' => $notes
        ]);
    }

    public function reject($approverId, $reason, $notes = null)
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $approverId,
            'rejected_reason' => $reason,
            'notes' => $notes
        ]);
    }

    public function calculatePriorityScore()
    {
        $score = 0;
        
        // Base score from application date (earlier applications get higher score)
        $daysAgo = Carbon::parse($this->application_date)->diffInDays(now());
        $score += max(0, 100 - $daysAgo);
        
        // Special requirements bonus
        if (!empty($this->special_requirements)) {
            $score += 20;
        }
        
        // Medical conditions bonus
        if (!empty($this->medical_conditions)) {
            $score += 30;
        }
        
        // Student year/level bonus (if available in student data)
        if ($this->student && $this->student->year_level) {
            $score += ($this->student->year_level * 5);
        }
        
        return $score;
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_SUBMITTED => 'blue',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_APPROVED => 'green',
            self::STATUS_REJECTED => 'red',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray'
        };
    }
}