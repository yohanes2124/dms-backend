<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TemporaryLeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'supervisor_id',
        'leave_type',
        'start_date',
        'end_date',
        'return_date',
        'destination',
        'emergency_contact_name',
        'emergency_contact_phone',
        'reason',
        'supervisor_approval',
        'status',
        'supervisor_notes',
        'approved_at',
        'returned_at',
        'room_secured',
        'security_checklist'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'return_date' => 'date',
        'approved_at' => 'datetime',
        'returned_at' => 'datetime',
        'room_secured' => 'boolean',
        'security_checklist' => 'array'
    ];

    // Leave types
    const TYPE_WEEKEND = 'weekend';
    const TYPE_HOLIDAY = 'holiday';
    const TYPE_EMERGENCY = 'emergency';
    const TYPE_MEDICAL = 'medical';
    const TYPE_FAMILY_VISIT = 'family_visit';
    const TYPE_OTHER = 'other';

    // Approval statuses
    const APPROVAL_PENDING = 'pending';
    const APPROVAL_APPROVED = 'approved';
    const APPROVAL_REJECTED = 'rejected';

    // Request statuses
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';
    const STATUS_OVERDUE = 'overdue';

    // Relationships
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('supervisor_approval', self::APPROVAL_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('supervisor_approval', self::APPROVAL_APPROVED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED])
                    ->where('start_date', '<=', now())
                    ->where('return_date', '>=', now());
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_APPROVED)
                    ->where('return_date', '<', now())
                    ->whereNull('returned_at');
    }

    public function scopeByStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeBySupervisor($query, $supervisorId)
    {
        return $query->where('supervisor_id', $supervisorId);
    }

    // Helper methods
    public function isPending()
    {
        return $this->supervisor_approval === self::APPROVAL_PENDING;
    }

    public function isApproved()
    {
        return $this->supervisor_approval === self::APPROVAL_APPROVED;
    }

    public function isRejected()
    {
        return $this->supervisor_approval === self::APPROVAL_REJECTED;
    }

    public function isActive()
    {
        return $this->isApproved() && 
               $this->start_date <= now() && 
               $this->return_date >= now() &&
               !$this->returned_at;
    }

    public function isOverdue()
    {
        return $this->isApproved() && 
               $this->return_date < now() && 
               !$this->returned_at;
    }

    public function canBeApproved()
    {
        return $this->status === self::STATUS_SUBMITTED && 
               $this->supervisor_approval === self::APPROVAL_PENDING;
    }

    public function canBeRejected()
    {
        return $this->status === self::STATUS_SUBMITTED && 
               $this->supervisor_approval === self::APPROVAL_PENDING;
    }

    public function getDurationInDays()
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getDaysUntilStart()
    {
        if ($this->start_date <= now()) {
            return 0;
        }
        return now()->diffInDays($this->start_date);
    }

    public function getDaysUntilReturn()
    {
        if ($this->return_date <= now()) {
            return 0;
        }
        return now()->diffInDays($this->return_date);
    }

    public function approve($supervisorId, $notes = null)
    {
        $this->update([
            'supervisor_approval' => self::APPROVAL_APPROVED,
            'status' => self::STATUS_APPROVED,
            'supervisor_id' => $supervisorId,
            'supervisor_notes' => $notes,
            'approved_at' => now()
        ]);

        // Auto-secure room if leave starts today or in the future
        if ($this->start_date <= now()->addDay()) {
            $this->secureRoom();
        }
    }

    public function reject($supervisorId, $reason)
    {
        $this->update([
            'supervisor_approval' => self::APPROVAL_REJECTED,
            'status' => self::STATUS_REJECTED,
            'supervisor_id' => $supervisorId,
            'supervisor_notes' => $reason
        ]);
    }

    public function markAsReturned()
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'returned_at' => now(),
            'room_secured' => false
        ]);
    }

    public function secureRoom()
    {
        $checklist = [
            'windows_locked' => true,
            'door_locked' => true,
            'valuables_secured' => true,
            'electricity_checked' => true,
            'water_taps_closed' => true,
            'room_cleaned' => true,
            'inspection_completed' => true,
            'secured_by' => auth()->id(),
            'secured_at' => now()->toISOString()
        ];

        $this->update([
            'room_secured' => true,
            'security_checklist' => $checklist
        ]);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_SUBMITTED => 'blue',
            self::STATUS_APPROVED => 'green',
            self::STATUS_REJECTED => 'red',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_OVERDUE => 'red',
            default => 'gray'
        };
    }

    public function getLeaveTypeDisplayAttribute()
    {
        return match($this->leave_type) {
            self::TYPE_WEEKEND => 'Weekend Leave',
            self::TYPE_HOLIDAY => 'Holiday Leave',
            self::TYPE_EMERGENCY => 'Emergency Leave',
            self::TYPE_MEDICAL => 'Medical Leave',
            self::TYPE_FAMILY_VISIT => 'Family Visit',
            self::TYPE_OTHER => 'Other',
            default => 'Unknown'
        };
    }

    public function getApprovalStatusDisplayAttribute()
    {
        return match($this->supervisor_approval) {
            self::APPROVAL_PENDING => 'Pending Approval',
            self::APPROVAL_APPROVED => 'Approved',
            self::APPROVAL_REJECTED => 'Rejected',
            default => 'Unknown'
        };
    }

    // Auto-update overdue status
    public static function updateOverdueRequests()
    {
        self::where('status', self::STATUS_APPROVED)
            ->where('return_date', '<', now())
            ->whereNull('returned_at')
            ->update(['status' => self::STATUS_OVERDUE]);
    }
}