<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(): bool
    {
        return true;
    }
    
    public function view(): bool
    {
        return true;
    }
    
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }
    
    public function update(User $user): bool
    {
        return $user->isAdmin();
    }
    
    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }
}
