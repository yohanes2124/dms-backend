<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'current_room_id',
        'requested_room_id',
        'request_type',
        'reason',
        'status',
        'priority',
        'requested_at',
        'processed_by',
        'processed_at',
        'approved_at',
        'rejection_reason',
        'notes'
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'approved_at' => 'datetime'
    ];

    // Request types
    const TYPE_ROOM_CHANGE = 'room_change';
    const TYPE_BLOCK_CHANGE = 'block_change';
    const TYPE_ROOMMATE_CHANGE = 'roommate_change';
    const TYPE_EMERGENCY_CHANGE = 'emergency_change';

    // Request statuses
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Priority levels
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Relationships
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function currentRoom()
    {
        return $this->belongsTo(Room::class, 'current_room_id');
    }

    public function requestedRoom()
    {
        return $this->belongsTo(Room::class, 'requested_room_id');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
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

    public function scopeByPriority($query, $priority = null)
    {
        if ($priority) {
            return $query->where('priority', $priority);
        }
        
        return $query->orderByRaw("
            CASE priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END
        ")->orderBy('requested_at', 'asc');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('request_type', $type);
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

    public function canBeProcessed()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function approve($processorId, $notes = null)
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'processed_by' => $processorId,
            'processed_at' => now(),
            'approved_at' => now(),
            'notes' => $notes
        ]);

        // If approved, process the room change
        if ($this->request_type === self::TYPE_ROOM_CHANGE && $this->requested_room_id) {
            $this->processRoomChange();
        }
    }

    public function reject($processorId, $reason, $notes = null)
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'processed_by' => $processorId,
            'processed_at' => now(),
            'rejection_reason' => $reason,
            'notes' => $notes
        ]);
    }

    public function complete()
    {
        $this->update([
            'status' => self::STATUS_COMPLETED
        ]);
    }

    protected function processRoomChange()
    {
        // Find current active assignment
        $currentAssignment = RoomAssignment::where('student_id', $this->student_id)
                                          ->where('status', RoomAssignment::STATUS_ACTIVE)
                                          ->first();

        if ($currentAssignment && $this->requestedRoom->canAccommodate()) {
            // Transfer to new room
            $newAssignment = $currentAssignment->transfer(
                $this->requested_room_id,
                $this->processed_by,
                "Room change request approved - Request ID: {$this->id}"
            );

            if ($newAssignment) {
                $this->complete();
                return true;
            }
        }

        return false;
    }

    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            self::PRIORITY_URGENT => 'red',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_MEDIUM => 'yellow',
            self::PRIORITY_LOW => 'green',
            default => 'gray'
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_APPROVED => 'green',
            self::STATUS_REJECTED => 'red',
            self::STATUS_COMPLETED => 'blue',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray'
        };
    }

    public function getRequestTypeDisplayAttribute()
    {
        return match($this->request_type) {
            self::TYPE_ROOM_CHANGE => 'Room Change',
            self::TYPE_BLOCK_CHANGE => 'Block Change',
            self::TYPE_ROOMMATE_CHANGE => 'Roommate Change',
            self::TYPE_EMERGENCY_CHANGE => 'Emergency Change',
            default => 'Unknown'
        };
    }
}