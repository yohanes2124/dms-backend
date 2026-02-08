<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_number',
        'block',
        'capacity',
        'current_occupancy',
        'room_type',
        'status',
        'description',
        'facilities'
    ];

    protected $casts = [
        'facilities' => 'array',
        'capacity' => 'integer',
        'current_occupancy' => 'integer'
    ];

    // Room types
    const TYPE_FOUR = 'four';
    const TYPE_SIX = 'six';

    // Room statuses
    const STATUS_AVAILABLE = 'available';
    const STATUS_OCCUPIED = 'occupied';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_RESERVED = 'reserved';

    // Relationships
    public function assignments()
    {
        return $this->hasMany(RoomAssignment::class);
    }

    public function roomAssignments()
    {
        return $this->hasMany(RoomAssignment::class);
    }

    public function applications()
    {
        return $this->hasMany(DormitoryApplication::class, 'preferred_room_id');
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE)
                    ->whereColumn('current_occupancy', '<', 'capacity');
    }

    public function scopeByBlock($query, $block)
    {
        return $query->where('block', $block);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('room_type', $type);
    }

    // Helper methods
    public function isAvailable()
    {
        return $this->status === self::STATUS_AVAILABLE && 
               $this->current_occupancy < $this->capacity;
    }

    public function isFull()
    {
        return $this->current_occupancy >= $this->capacity;
    }

    public function getAvailableSpaces()
    {
        return $this->capacity - $this->current_occupancy;
    }

    public function canAccommodate($numberOfStudents = 1)
    {
        return $this->isAvailable() && 
               $this->getAvailableSpaces() >= $numberOfStudents;
    }

    public function incrementOccupancy()
    {
        $this->increment('current_occupancy');
        
        if ($this->current_occupancy >= $this->capacity) {
            $this->update(['status' => self::STATUS_OCCUPIED]);
        }
    }

    public function decrementOccupancy()
    {
        $this->decrement('current_occupancy');
        
        if ($this->current_occupancy < $this->capacity && $this->status === self::STATUS_OCCUPIED) {
            $this->update(['status' => self::STATUS_AVAILABLE]);
        }
    }

    public function getCurrentOccupants()
    {
        return $this->assignments()
                   ->with('student')
                   ->where('status', RoomAssignment::STATUS_ACTIVE)
                   ->get()
                   ->pluck('student');
    }
}