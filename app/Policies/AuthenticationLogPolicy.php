<?php

namespace App\Policies;

use App\Models\User;
use Rappasoft\LaravelAuthenticationLog\Models\AuthenticationLog;

class AuthenticationLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, AuthenticationLog $authenticationLog): bool
    {
        return $user->hasRole('super_admin');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuthenticationLog $authenticationLog): bool
    {
        return false;
    }

    public function delete(User $user, AuthenticationLog $authenticationLog): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, AuthenticationLog $authenticationLog): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, AuthenticationLog $authenticationLog): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, AuthenticationLog $authenticationLog): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
