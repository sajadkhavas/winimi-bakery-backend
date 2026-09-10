<?php

namespace App\Policies;

use App\Models\NavigationItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NavigationItemPolicy
{
    use HandlesAuthorization;

    private function isPanelOperator(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'panel_user']);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->isPanelOperator($user) || $user->can('view_any_navigation::item');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, NavigationItem $navigationItem): bool
    {
        return $this->isPanelOperator($user) || $user->can('view_navigation::item');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->isPanelOperator($user) || $user->can('create_navigation::item');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, NavigationItem $navigationItem): bool
    {
        return $this->isPanelOperator($user) || $user->can('update_navigation::item');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, NavigationItem $navigationItem): bool
    {
        return $this->isPanelOperator($user) || $user->can('delete_navigation::item');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $this->isPanelOperator($user) || $user->can('delete_any_navigation::item');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, NavigationItem $navigationItem): bool
    {
        return $user->can('force_delete_navigation::item');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_navigation::item');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, NavigationItem $navigationItem): bool
    {
        return $user->can('restore_navigation::item');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_navigation::item');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, NavigationItem $navigationItem): bool
    {
        return $user->can('replicate_navigation::item');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $this->isPanelOperator($user) || $user->can('reorder_navigation::item');
    }
}
