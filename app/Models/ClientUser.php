<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientUser extends User
{
    protected $fillable = [
        'user_id'
    ];
}
