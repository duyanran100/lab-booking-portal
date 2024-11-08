<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($data['room_id']) {
            $data['type'] = 'room';
        }
        if ($data['server_id']) {
            $data['type'] = 'server';
        }
        if (Carbon::parse($data['start_time'])->isSameDay($data['end_time'])) {
            $data['time_frame_type'] = 'single';
            $data['single_date'] = Carbon::parse($data['start_time'])->toDateString();
            
            $startHour = Carbon::parse($data['start_time'])->hour;
            $endHour = Carbon::parse($data['end_time'])->hour;

            if ($startHour >= 0 && $endHour <= 12) {
                $data['single_time_slot'] = '00:00-12:00';
            } else {
                $data['single_time_slot'] = '12:00-24:00';
            }
        } else {
            $data['time_frame_type'] = 'multiple';
            $data['start_date'] = Carbon::parse($data['start_time'])->toDateString();
            $data['end_date'] = Carbon::parse($data['end_time'])->toDateString();

            $startHour = Carbon::parse($data['start_time'])->hour;
            $endHour = Carbon::parse($data['end_time'])->hour;

            $data['start_time_slot'] = $startHour >= 0 && $startHour <= 12 ? '00:00-12:00' : '12:00-24:00';
            $data['end_time_slot'] = $endHour >= 0 && $endHour <= 12 ? '00:00-12:00' : '12:00-24:00';
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
