<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'professional_profile_id',
        'name',
        'duration_minutes',
        'price',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
    ];

    public function professionalProfile()
    {
        return $this->belongsTo(ProfessionalProfile::class);
    }
}
