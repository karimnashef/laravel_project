<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->id === $model->id
    }

    public function restore(User $user, User $model): bool
    {
        return ($user->role) === 'admin';
    }

    public function forceDelete(User $user, User $model): bool
    {
        return ($user->role) === 'admin';
    }

    public function block(User $user, User $model): bool
    {
        return ($user->role) === 'admin';
    }

    public function unblock(User $user, User $model): bool
    {
        return ($user->role) === 'admin';
    }

    public function switch(User $user, User $model): bool
    {
        return ($user->name === $model->name);
    }
}
