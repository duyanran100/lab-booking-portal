<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Enums\BookingStatusEnum;
use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use Filament\Resources\Components\Tab;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make(),
            'my_bookings' => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', auth()->id())),
            'room' => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('room_id')->where('room_id', '!=', 0)),
            'server' => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('server_id')->where('server_id', '!=', 0)),
        ];

        if (auth()->user()->isAdmin()) {
            $tabs['pending'] = Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('status', BookingStatusEnum::PENDING));
            $tabs['rejected'] = Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('status', BookingStatusEnum::REJECTED));
        }

        return $tabs;
    }
}
