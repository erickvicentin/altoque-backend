<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'professional_profile_id',
        'client_id',
        'service_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'custom_title',
        'notes',
    ];

    public function professionalProfile()
    {
        return $this->belongsTo(ProfessionalProfile::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
