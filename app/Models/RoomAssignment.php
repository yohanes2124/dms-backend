<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'room_id',
        'application_id',
        'assigned_by',
        'assigned_at',
        'check_in_date',
        'check_out_date',
        'status',
        'semester',
        'academic_year',
        'notes'
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'check_in_date' => 'date',
        'check_out_date' => 'date'
    ];

    // Assignment statuses
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_TRANSFERRED = 'transferred';

    // Relationships
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function application()
    {
        return $this->belongsTo(DormitoryApplication::class, 'application_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function clearanceRecord()
    {
        return $this->hasOne(ClearanceRecord::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByRoom($query, $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    public function scopeByStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeCurrentSemester($query, $semester = null, $academicYear = null)
    {
        $semester = $semester ?? config('app.current_semester');
        $academicYear = $academicYear ?? config('app.current_academic_year');
        
        return $query->where('semester', $semester)
                    ->where('academic_year', $academicYear);
    }

    // Helper methods
    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canCheckIn()
    {
        return $this->status === self::STATUS_ASSIGNED && 
               (!$this->check_in_date || $this->check_in_date <= now()->toDateString());
    }

    public function canCheckOut()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function checkIn($checkInDate = null)
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'check_in_date' => $checkInDate ?? now()->toDateString()
        ]);

        // Update room occupancy
        $this->room->incrementOccupancy();
    }

    public function checkOut($checkOutDate = null)
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'check_out_date' => $checkOutDate ?? now()->toDateString()
        ]);

        // Update room occupancy
        $this->room->decrementOccupancy();
    }

    public function transfer($newRoomId, $transferredBy, $notes = null)
    {
        // Mark current assignment as transferred
        $this->update([
            'status' => self::STATUS_TRANSFERRED,
            'check_out_date' => now()->toDateString(),
            'notes' => $notes
        ]);

        // Decrease occupancy of current room
        $this->room->decrementOccupancy();

        // Create new assignment
        $newAssignment = self::create([
            'student_id' => $this->student_id,
            'room_id' => $newRoomId,
            'assigned_by' => $transferredBy,
            'assigned_at' => now(),
            'check_in_date' => now()->toDateString(),
            'status' => self::STATUS_ACTIVE,
            'semester' => $this->semester,
            'academic_year' => $this->academic_year,
            'notes' => "Transferred from Room {$this->room->room_number}"
        ]);

        // Increase occupancy of new room
        $newAssignment->room->incrementOccupancy();

        return $newAssignment;
    }

    public function getDurationAttribute()
    {
        if (!$this->check_in_date) {
            return null;
        }

        $endDate = $this->check_out_date ?? now()->toDateString();
        return \Carbon\Carbon::parse($this->check_in_date)
                           ->diffInDays(\Carbon\Carbon::parse($endDate));
    }

    public function getRoomDetailsAttribute()
    {
        return [
            'room_number' => $this->room->room_number,
            'block' => $this->room->block,
            'room_type' => $this->room->room_type,
            'capacity' => $this->room->capacity
        ];
    }
}