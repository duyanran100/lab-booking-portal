<?php

namespace App\Models;

use App\Enums\BookingStatusEnum;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\Event;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model implements Eventable
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'purpose',
        'status',
        'user_id',
        'room_id',
        'server_id',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'status' => BookingStatusEnum::class,
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            if (auth()->check() && auth()->user()->isAdmin()) {
                $booking->status = BookingStatusEnum::APPROVED;
            }
        });
    }

    public function user() 
    {
        return $this->belongsTo(User::class);
    }
    
    public function room() 
    {
        return $this->belongsTo(Room::class);
    }
    
    public function server() 
    {
        return $this->belongsTo(Server::class);
    }    

    public function toEvent(): Event|array
    {
        $backgroundColor = match($this->status) {
            BookingStatusEnum::PENDING => '#fbbf24',
            BookingStatusEnum::REJECTED => '#ef4444',
            BookingStatusEnum::APPROVED => '#10b981',
            default => '#4b0082',
        };

        return Event::make($this)
            ->title($this->getBookingTitle())
            ->start($this->start_time->shiftTimezone('UTC'))
            ->end($this->end_time->shiftTimezone('UTC'))
            ->backgroundColor($backgroundColor)
            ->textColor('#ffffff');
    }
    
    public function getBookingTitle(): string
    {
        if ($this->room_id) {
            return "Room: {$this->room->name}";
        }
    
        if ($this->server_id) {
            return "Server: {$this->server->name}";
        }
    
        return match(true) {
            isset($this->room_id) => "🔵 {$this->room->name}",
            isset($this->server_id) => "🟢 {$this->server->name}",
            default => "Booking #{$this->id}"
        };
    }
}
