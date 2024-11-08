<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatusEnum;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationGroup = 'Booking Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
            ]);
    }
    

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                if (auth()->user()->isGuest()) {
                    return $query->where('status', '!=', BookingStatusEnum::REJECTED);
                }
            })
            ->columns([
                TextColumn::make('user.preferred_name')
                    ->label('User')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('room.name')
                    ->label('Room')
                    ->sortable()
                    ->searchable()
                    ->default('-'),

                TextColumn::make('server.name')
                    ->label('Server')
                    ->sortable()
                    ->searchable()
                    ->default('-'),

                TextColumn::make('start_time')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('end_time')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'primary' => BookingStatusEnum::PENDING,
                        'success' => BookingStatusEnum::APPROVED,
                        'danger' => BookingStatusEnum::REJECTED,
                    ])
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('time_status')
                    ->placeholder('All bookings')
                    ->trueLabel('Expired bookings')
                    ->falseLabel('Incoming bookings')
                    ->default(false)
                    ->queries(
                        true: fn (Builder $query) => $query->where('end_time', '<=', now()),
                        false: fn (Builder $query) => $query->where('end_time', '>=', now()),
                        blank: fn (Builder $query) => $query,
                    ),
                ...auth()->user()->isAdmin() ? [
                    Tables\Filters\SelectFilter::make('status')
                        ->label('Approval status')
                        ->options([
                            BookingStatusEnum::PENDING->value => BookingStatusEnum::PENDING->label(),
                            BookingStatusEnum::APPROVED->value => BookingStatusEnum::APPROVED->label(),
                            BookingStatusEnum::REJECTED->value => BookingStatusEnum::REJECTED->label(),
                        ])
                ] : [],
                Tables\Filters\Filter::make('duration')
                    ->form([
                        DatePicker::make('start_time'),
                        DatePicker::make('end_time'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['start_time'],
                                fn (Builder $query, $date): Builder => $query->whereDate('start_time', '>=', $date),
                            )
                            ->when(
                                $data['end_time'],
                                fn (Builder $query, $date): Builder => $query->whereDate('end_time', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-s-check')
                    ->requiresConfirmation()
                    ->action(fn (Booking $record) => $record->update(['status' => BookingStatusEnum::APPROVED->value]))
                    ->color('success')
                    ->visible(fn (Booking $record) => auth()->user()->isAdmin() && $record->status === BookingStatusEnum::PENDING),
                
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->action(fn (Booking $record) => $record->update(['status' => BookingStatusEnum::REJECTED->value]))
                    ->color('danger')
                    ->visible(fn (Booking $record) => auth()->user()->isAdmin() && $record->status === BookingStatusEnum::PENDING),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
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
