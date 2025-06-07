<?php

use App\Enums\UserType;
use App\Models\User;

use function PHPStan\Testing\assertType;

function test(User $user): void
{
    assertType(UserType::class, $user->type);
    assertType('int', $user->team_id);
    assertType('int|null', $user->current_team_id);
    assertType('*ERROR*', $user->missing_id);
}
