<?php

namespace App\Policies;

use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(): bool
    {
        return true;
    }
    
    public function view(User $user, Booking $booking): bool
    {
        if ($user->isGuest()) {
            return in_array($booking->status, [
                    BookingStatusEnum::PENDING->value,
                    BookingStatusEnum::APPROVED->value
                ]) && $booking->end_time > now();
        }
    
        return $user->isAdmin();
    }
    
    public function create(): bool
    {
        return true;
    }
    
    public function update(User $user): bool
    {
        return $user->isAdmin();
    }
    
    public function delete(User $user, Booking $booking): bool
    {
        return $user->isAdmin() || $booking->user->id === $user->id;
    }
}
