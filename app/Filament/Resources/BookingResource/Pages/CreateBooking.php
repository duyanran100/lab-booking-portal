<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Enums\BookingStatusEnum;
use App\Filament\Resources\BookingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        if (auth()->user()->isAdmin()) {
            $data['status'] = BookingStatusEnum::APPROVED;
        }

        return static::getModel()::create($data);
    }
}
