<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\User;
use RuntimeException;

class MissingWorkspaceException extends RuntimeException
{
    public function __construct(User $user)
    {
        parent::__construct("User {$user->id} has no current workspace.");
    }
}
