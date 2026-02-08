<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'gender',
        'total_rooms',
        'floors',
        'facilities',
        'status'
    ];

    protected $casts = [
        'facilities' => 'array',
        'total_rooms' => 'integer',
        'floors' => 'integer'
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_INACTIVE = 'inactive';

    /**
     * Get all rooms in this block
     */
    public function rooms()
    {
        return $this->hasMany(Room::class, 'block', 'name');
    }

    /**
     * Get occupied rooms count
     */
    public function getOccupiedRoomsAttribute()
    {
        return $this->rooms()->where('current_occupancy', '>', 0)->count();
    }

    /**
     * Get occupancy percentage
     */
    public function getOccupancyPercentageAttribute()
    {
        if ($this->total_rooms == 0) return 0;
        return round(($this->occupied_rooms / $this->total_rooms) * 100);
    }

    /**
     * Get supervisors assigned to this block
     */
    public function supervisors()
    {
        return $this->hasMany(User::class, 'assigned_block', 'name')
                    ->where('user_type', User::TYPE_SUPERVISOR);
    }

    /**
     * Get supervisor count for this block
     */
    public function getSupervisorCountAttribute()
    {
        return $this->supervisors()->count();
    }
}