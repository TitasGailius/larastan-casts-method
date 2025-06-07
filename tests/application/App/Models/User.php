<?php

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    /**
     * Get the attributes that should be cast.
     */
    protected function casts()
    {
        return [
            'type' => UserType::class,
            'team_id' => 'integer',
            'current_team_id' => 'integer',
            'missing_id' => 'integer',
        ];
    }
}
