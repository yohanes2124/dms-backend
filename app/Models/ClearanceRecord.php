<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClearanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'room_assignment_id',
        'clearance_type',
        'status',
        'initiated_by',
        'initiated_at',
        'completed_by',
        'completed_at',
        'room_condition',
        'damages_reported',
        'damages_cost',
        'items_left_behind',
        'keys_returned',
        'cleaning_status',
        'final_inspection_notes',
        'clearance_certificate_issued',
        'certificate_number'
    ];

    protected $casts = [
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'damages_reported' => 'array',
        'items_left_behind' => 'array',
        'damages_cost' => 'decimal:2',
        'keys_returned' => 'boolean',
        'clearance_certificate_issued' => 'boolean'
    ];

    // Clearance types
    const TYPE_SEMESTER_END = 'semester_end';
    const TYPE_GRADUATION = 'graduation';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_DISCIPLINARY = 'disciplinary';
    const TYPE_VOLUNTARY = 'voluntary';

    // Clearance statuses
    const STATUS_INITIATED = 'initiated';
    const STATUS_INSPECTION_PENDING = 'inspection_pending';
    const STATUS_INSPECTION_COMPLETED = 'inspection_completed';
    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Room conditions
    const CONDITION_EXCELLENT = 'excellent';
    const CONDITION_GOOD = 'good';
    const CONDITION_FAIR = 'fair';
    const CONDITION_POOR = 'poor';
    const CONDITION_DAMAGED = 'damaged';

    // Cleaning statuses
    const CLEANING_EXCELLENT = 'excellent';
    const CLEANING_GOOD = 'good';
    const CLEANING_NEEDS_IMPROVEMENT = 'needs_improvement';
    const CLEANING_POOR = 'poor';

    // Relationships
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function roomAssignment()
    {
        return $this->belongsTo(RoomAssignment::class);
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->whereIn('status', [
            self::STATUS_INITIATED,
            self::STATUS_INSPECTION_PENDING,
            self::STATUS_INSPECTION_COMPLETED,
            self::STATUS_PENDING_PAYMENT
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('clearance_type', $type);
    }

    public function scopeWithDamages($query)
    {
        return $query->whereNotNull('damages_reported')
                    ->where('damages_cost', '>', 0);
    }

    // Helper methods
    public function isPending()
    {
        return in_array($this->status, [
            self::STATUS_INITIATED,
            self::STATUS_INSPECTION_PENDING,
            self::STATUS_INSPECTION_COMPLETED,
            self::STATUS_PENDING_PAYMENT
        ]);
    }

    public function isCompleted()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canBeInspected()
    {
        return in_array($this->status, [
            self::STATUS_INITIATED,
            self::STATUS_INSPECTION_PENDING
        ]);
    }

    public function canBeCompleted()
    {
        return $this->status === self::STATUS_INSPECTION_COMPLETED && 
               $this->keys_returned && 
               ($this->damages_cost == 0 || $this->status === self::STATUS_PENDING_PAYMENT);
    }

    public function completeInspection($inspectorId, $inspectionData)
    {
        $this->update([
            'status' => $inspectionData['damages_cost'] > 0 ? 
                       self::STATUS_PENDING_PAYMENT : 
                       self::STATUS_INSPECTION_COMPLETED,
            'room_condition' => $inspectionData['room_condition'],
            'damages_reported' => $inspectionData['damages_reported'] ?? [],
            'damages_cost' => $inspectionData['damages_cost'] ?? 0,
            'items_left_behind' => $inspectionData['items_left_behind'] ?? [],
            'keys_returned' => $inspectionData['keys_returned'] ?? false,
            'cleaning_status' => $inspectionData['cleaning_status'],
            'final_inspection_notes' => $inspectionData['notes'] ?? null
        ]);
    }

    public function completeClearance($completedById)
    {
        $certificateNumber = $this->generateCertificateNumber();
        
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_by' => $completedById,
            'completed_at' => now(),
            'clearance_certificate_issued' => true,
            'certificate_number' => $certificateNumber
        ]);

        return $certificateNumber;
    }

    protected function generateCertificateNumber()
    {
        $year = now()->year;
        $month = now()->format('m');
        $sequence = str_pad($this->id, 4, '0', STR_PAD_LEFT);
        
        return "CLR-{$year}{$month}-{$sequence}";
    }

    public function hasDamages()
    {
        return !empty($this->damages_reported) && $this->damages_cost > 0;
    }

    public function hasItemsLeftBehind()
    {
        return !empty($this->items_left_behind);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_INITIATED => 'blue',
            self::STATUS_INSPECTION_PENDING => 'yellow',
            self::STATUS_INSPECTION_COMPLETED => 'orange',
            self::STATUS_PENDING_PAYMENT => 'red',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray'
        };
    }

    public function getClearanceTypeDisplayAttribute()
    {
        return match($this->clearance_type) {
            self::TYPE_SEMESTER_END => 'Semester End',
            self::TYPE_GRADUATION => 'Graduation',
            self::TYPE_TRANSFER => 'Transfer',
            self::TYPE_DISCIPLINARY => 'Disciplinary',
            self::TYPE_VOLUNTARY => 'Voluntary',
            default => 'Unknown'
        };
    }

    public function getTotalDaysPendingAttribute()
    {
        if ($this->isCompleted()) {
            return $this->initiated_at->diffInDays($this->completed_at);
        }
        
        return $this->initiated_at->diffInDays(now());
    }
}