<?php

namespace App\Models;

use App\Enums\BookingStatusEnum;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
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
    ];

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
}
