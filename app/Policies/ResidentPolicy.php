<?php

namespace App\Policies;

use App\Models\Resident;
use App\Models\User;

class ResidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can('residents.view');
    }

    public function view(User $user, Resident $resident): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->can('residents.manage');
    }

    public function update(User $user, Resident $resident): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Resident $resident): bool
    {
        return $this->update($user, $resident);
    }
}
