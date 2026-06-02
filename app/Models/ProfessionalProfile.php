<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalProfile extends Model
{
    protected $fillable = ['user_id', 'profession', 'has_physical_shop', 'shop_address'];
}