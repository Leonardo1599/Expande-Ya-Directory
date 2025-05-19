<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessUser extends User
{
    protected $fillable = [
        'user_id',
        'empresa'
    ];
}
