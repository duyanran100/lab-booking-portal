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
    
    public function view(): bool
    {
        return true;
    }
    
    public function create(): bool
    {
        return true;
    }
    
    public function update(User $user, Booking $booking): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
    
        return $user->id === $booking->user_id && $booking->status === BookingStatusEnum::PENDING;
    }
    
    public function delete(User $user, Booking $booking): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
    
        return $user->id === $booking->user_id && $booking->status === BookingStatusEnum::PENDING;
    }    
}
