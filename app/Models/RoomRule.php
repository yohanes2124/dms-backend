<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'title',
        'description',
        'order_number',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_number' => 'integer',
    ];

    // Category constants
    const CATEGORY_GENERAL = 'General';
    const CATEGORY_SAFETY = 'Safety';
    const CATEGORY_VISITORS = 'Visitors';
    const CATEGORY_QUIET_HOURS = 'Quiet Hours';
    const CATEGORY_CLEANLINESS = 'Cleanliness';

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_number')->orderBy('id');
    }

    // Helper methods
    public function isActive()
    {
        return $this->is_active;
    }

    public static function getCategories()
    {
        return [
            self::CATEGORY_GENERAL,
            self::CATEGORY_SAFETY,
            self::CATEGORY_VISITORS,
            self::CATEGORY_QUIET_HOURS,
            self::CATEGORY_CLEANLINESS,
        ];
    }
}
