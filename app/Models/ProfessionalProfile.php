<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalProfile extends Model
{
    protected $fillable = [
        'user_id', 
        'profession', 
        'has_physical_shop', 
        'shop_address',
        'open_time_1',
        'close_time_1',
        'has_second_range',
        'open_time_2',
        'close_time_2',
    ];
}