<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = ['user_id', 'address_line', 'alias', 'is_default'];

    protected $casts = [
        'is_default' => 'boolean',
    ];
}