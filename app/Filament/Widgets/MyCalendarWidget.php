<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use Filament\Tables\Actions\Concerns\InteractsWithRecords;
use \Guava\Calendar\Widgets\CalendarWidget;
use Illuminate\Support\Collection;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Guava\Calendar\Actions\CreateAction;
use Illuminate\Support\Carbon;

class MyCalendarWidget extends CalendarWidget
{
    use InteractsWithRecords;

    protected bool $eventClickEnabled = true;

    protected bool $dateClickEnabled = true;

    protected bool $dateSelectEnabled = true;

    public function getEvents(array $fetchInfo = []): Collection | array
    {
        return Booking::where('status', '!=', BookingStatusEnum::REJECTED)->get();
    }
    

    public function getEventContent(): null | string | array
    {
        return [
            Booking::class => view('components.calendar.events.booking'),
        ];
    }

    public function getDateClickContextMenuActions(): array
    {
        return [
            CreateAction::make('click_creation')
                ->model(Booking::class)
                ->mountUsing(function ($arguments, $form) {
                    $date = data_get($arguments, 'dateStr');
                    $form->fill([
                        'user_id' => auth()->user()->id,
                        'time_frame_type' => 'single',
                        'single_date' => $date,
                    ]);
                }),
        ];
    }

    public function getDateSelectContextMenuActions(): array
    {
        return [
            CreateAction::make('select_creation')
                ->model(Booking::class)
                ->mountUsing(fn ($arguments, $form) => $form->fill([
                    'user_id' => auth()->user()->id,
                    'time_frame_type' => 'multiple',
                    'start_date' => data_get($arguments, 'startStr'),
                    'end_date' => data_get($arguments, 'endStr'),
                ])),
        ];
    }

    public function getEventClickContextMenuActions(): array
    {
        if (auth()->user()->isAdmin()) {
            return [
                $this->editAction()->mutateRecordDataUsing(function ($data) {
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
                }),
                $this->deleteAction(),
            ];
        } else {
            return [
                $this->viewAction()->mutateRecordDataUsing(function ($data) {
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
                }),
            ];
        }
    }

    public function getSchema(?string $model = null): ?array
    {
        return [
                Select::make('user_id')
                    ->label('User')
                    ->default(auth()->user()->id)
                    ->disabled(auth()->user()->isGuest())
                    ->relationship('user', 'email')
                    ->required()
                    ->rules(['exists:users,id']),

                Radio::make('type')
                    ->label('Booking Type')
                    ->options([
                        'room' => 'Room',
                        'server' => 'Server',
                    ])
                    ->live()
                    ->required()
                    ->afterStateUpdated(function (Set $set) {
                        $set('room_id', null);
                        $set('server_id', null);
                    })
                    ->columnSpanFull()
                    ->columns(2),
    
                Select::make('room_id')
                    ->label('Room')
                    ->relationship('room', 'name')
                    ->required()
                    ->live()
                    ->visible(fn (Get $get) => $get('type') === 'room'),
                
                Select::make('server_id')
                    ->label('Server')
                    ->relationship('server', 'name')
                    ->required()
                    ->live()
                    ->visible(fn (Get $get) => $get('type') === 'server'),
                
                TextInput::make('purpose')
                    ->label('Purpose')
                    ->required()
                    ->rules(['required', 'string', 'max:255'])
                    ->placeholder('Enter the purpose of your booking')
                    ->helperText('Briefly describe why you need this resource')
                    ->columnSpanFull(),
            
                Radio::make('time_frame_type')
                    ->label('Booking Time Frame')
                    ->options([
                        'single' => 'Single Time Frame',
                        'multiple' => 'Multiple Time Frames',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('single_date', null);
                        $set('single_time_slot', null);
                        $set('start_date', null);
                        $set('start_time_slot', null);
                        $set('end_date', null);
                        $set('end_time_slot', null);
                    })
                    ->columnSpanFull(),
    
                // For Single Time Frame Selection
                DatePicker::make('single_date')
                    ->label('Select Date')
                    ->required()
                    ->live()
                    ->visible(fn (Get $get) => $get('time_frame_type') === 'single')
                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                        // Set start_time and end_time based on single date and time slot
                        $hours = $get('single_time_slot');
                        $date = $state;
                        if ($date && $hours) {
                            [$startHour, $endHour] = explode('-', $hours);
                            $startDateTime = Carbon::parse($date)->setTime((int)$startHour, 0)->toDateTimeString();
                            $endDateTime = Carbon::parse($date)->setTime((int)$endHour, 0)->toDateTimeString();
    
                            $set('start_time', $startDateTime);
                            $set('end_time', $endDateTime);

                            self::validateBookingAvailability($get, $set, $startDateTime, $endDateTime);
                        }
                    }),
    
                Radio::make('single_time_slot')
                    ->label('Select Time Slot')
                    ->options([
                        '00:00-12:00' => 'Night (00:00 - 12:00)',
                        '12:00-24:00' => 'Day (12:00 - 24:00)',
                    ])
                    ->required()
                    ->live()
                    ->visible(fn (Get $get) => $get('time_frame_type') === 'single')
                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                        // Set start_time and end_time based on single date and time slot
                        $date = $get('single_date');
                        $hours = $state;
                        if ($date && $hours) {
                            [$startHour, $endHour] = explode('-', $hours);
                            $startDateTime = Carbon::parse($date)->setTime((int)$startHour, 0)->toDateTimeString();
                            $endDateTime = Carbon::parse($date)->setTime((int)$endHour, 0)->toDateTimeString();
    
                            $set('start_time', $startDateTime);
                            $set('end_time', $endDateTime);

                            self::validateBookingAvailability($get, $set, $startDateTime, $endDateTime);
                        }
                    }),
    
                // For Multiple Time Frames Selection
                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->required()
                    ->live()
                    ->visible(fn (Get $get) => $get('time_frame_type') === 'multiple')
                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                        // Set start_time and end_time based on start_date, start_time_slot, end_date, end_time_slot
                        $startDate = $state;
                        $endDate = $get('end_date');
                        $startTimeSlot = $get('start_time_slot');
                        $endTimeSlot = $get('end_time_slot');
    
                        if ($startDate && $endDate && $startTimeSlot && $endTimeSlot) {
                            [$startHour] = explode('-', $startTimeSlot);
                            [$_, $endHour] = explode('-', $endTimeSlot);
                            $startDateTime = Carbon::parse($startDate)->setTime((int)$startHour, 0)->toDateTimeString();
                            $endDateTime = Carbon::parse($endDate)->setTime((int)$endHour, 0)->toDateTimeString();
    
                            $set('start_time', $startDateTime);
                            $set('end_time', $endDateTime);
    
                            // Trigger validation once end_time is set
                            self::validateBookingAvailability($get, $set, $startDateTime, $endDateTime);
                        }
                    }),
    
                Radio::make('start_time_slot')
                    ->label('Select Start Time Slot')
                    ->options([
                        '00:00-12:00' => 'Night (00:00 - 12:00)',
                        '12:00-24:00' => 'Day (12:00 - 24:00)',
                    ])
                    ->required()
                    ->live()
                    ->visible(fn (Get $get) => $get('time_frame_type') === 'multiple')
                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                        // Set start_time and end_time based on start_date, start_time_slot, end_date, end_time_slot
                        $startDate = $get('start_date');
                        $endDate = $get('end_date');
                        $startTimeSlot = $state;
                        $endTimeSlot = $get('end_time_slot');
    
                        if ($startDate && $endDate && $startTimeSlot && $endTimeSlot) {
                            [$startHour] = explode('-', $startTimeSlot);
                            [$_, $endHour] = explode('-', $endTimeSlot);
                            $startDateTime = Carbon::parse($startDate)->setTime((int)$startHour, 0)->toDateTimeString();
                            $endDateTime = Carbon::parse($endDate)->setTime((int)$endHour, 0)->toDateTimeString();
    
                            $set('start_time', $startDateTime);
                            $set('end_time', $endDateTime);
    
                            // Trigger validation once end_time is set
                            self::validateBookingAvailability($get, $set, $startDateTime, $endDateTime);
                        }
                    }),
    
                DatePicker::make('end_date')
                    ->label('End Date')
                    ->required()
                    ->live()
                    ->visible(fn (Get $get) => $get('time_frame_type') === 'multiple')
                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                        // Set start_time and end_time based on start_date, start_time_slot, end_date, end_time_slot
                        $startDate = $get('start_date');
                        $endDate = $state;
                        $startTimeSlot = $get('start_time_slot');
                        $endTimeSlot = $get('end_time_slot');
    
                        if ($startDate && $endDate && $startTimeSlot && $endTimeSlot) {
                            [$startHour] = explode('-', $startTimeSlot);
                            [$_, $endHour] = explode('-', $endTimeSlot);
                            $startDateTime = Carbon::parse($startDate)->setTime((int)$startHour, 0)->toDateTimeString();
                            $endDateTime = Carbon::parse($endDate)->setTime((int)$endHour, 0)->toDateTimeString();
    
                            $set('start_time', $startDateTime);
                            $set('end_time', $endDateTime);
    
                            // Trigger validation once end_time is set
                            self::validateBookingAvailability($get, $set, $startDateTime, $endDateTime);
                        }
                    }),
    
                Radio::make('end_time_slot')
                    ->label('Select End Time Slot')
                    ->options([
                        '00:00-12:00' => 'Night (00:00 - 12:00)',
                        '12:00-24:00' => 'Day (12:00 - 24:00)',
                    ])
                    ->required()
                    ->live()
                    ->visible(fn (Get $get) => $get('time_frame_type') === 'multiple')
                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                        // Set start_time and end_time based on start_date, start_time_slot, end_date, end_time_slot
                        $startDate = $get('start_date');
                        $endDate = $get('end_date');
                        $startTimeSlot = $get('start_time_slot');
                        $endTimeSlot = $state;
    
                        if ($startDate && $endDate && $startTimeSlot && $endTimeSlot) {
                            [$startHour] = explode('-', $startTimeSlot);
                            [$_, $endHour] = explode('-', $endTimeSlot);
                            $startDateTime = Carbon::parse($startDate)->setTime((int)$startHour, 0)->toDateTimeString();
                            $endDateTime = Carbon::parse($endDate)->setTime((int)$endHour, 0)->toDateTimeString();
    
                            $set('start_time', $startDateTime);
                            $set('end_time', $endDateTime);
    
                            // Trigger validation once end_time is set
                            self::validateBookingAvailability($get, $set, $startDateTime, $endDateTime);
                        }
                    }),

                Hidden::make('start_time')
                    ->required()
                    ->live(),     

                Hidden::make('end_time')
                    ->required()
                    ->live(),
        ];
    }

    protected static function validateBookingAvailability(Get $get, Set $set, $startDateTime, $endDateTime)
    {
        if ($endDateTime <= $startDateTime) {
            Notification::make()
                ->danger()
                ->title('Booking Time Invalid')
                ->body("The selected start time period is after end time. Please pick a valid time.")
                ->persistent()
                ->send();

            $set('start_time', null);
            $set('end_time', null);
        }

        if ($endDateTime < now()) {
            Notification::make()
                ->danger()
                ->title('Booking Time Invalid')
                ->body("The selected booking time period is in the past. Please pick a valid time.")
                ->persistent()
                ->send();

            $set('start_time', null);
            $set('end_time', null);
        }

        $type = $get('type');

        if ($type && $startDateTime && $endDateTime) {
            $query = Booking::query()->where('status', '!=', BookingStatusEnum::REJECTED)->where('end_time', '>', now());

            if ($type === 'room') {
                $query->where('room_id', $get('room_id'));
            } else {
                $query->where('server_id', $get('server_id'));
            }

            // Check for overlapping bookings
            $query->where(function ($q) use ($startDateTime, $endDateTime) {
                $q->where(function ($q) use ($startDateTime, $endDateTime) {
                    $q->where('start_time', '>', $startDateTime)
                       ->where('start_time', '<', $endDateTime);
                })->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                    $q->where('end_time', '>', $startDateTime)
                       ->where('end_time', '<', $endDateTime);
                })->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                    $q->where('start_time', '<=', $startDateTime)
                       ->where('end_time', '>=', $endDateTime);
                });
            });

            if ($query->exists()) {
                $resourceType = ucfirst($type);
                Notification::make()
                    ->danger()
                    ->title('Booking Conflict')
                    ->body("This {$resourceType} is already booked during the selected time period.")
                    ->persistent()
                    ->send();

                $set('start_time', null);
                $set('end_time', null);
            }
        }
    }
}
