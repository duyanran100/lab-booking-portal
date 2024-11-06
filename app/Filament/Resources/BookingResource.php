<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatusEnum;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
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
                    ->relationship('user', 'name')
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
                    ->visible(fn (Get $get) => $get('type') === 'room'),
                
                Select::make('server_id')
                    ->label('Server')
                    ->relationship('server', 'name')
                    ->required()
                    ->visible(fn (Get $get) => $get('type') === 'server'),
                
                TextInput::make('purpose')
                    ->label('Purpose')
                    ->required()
                    ->rules(['required', 'string', 'max:255'])
                    ->placeholder('Enter the purpose of your booking')
                    ->helperText('Briefly describe why you need this resource')
                    ->columnSpanFull(),
            
                DateTimePicker::make('start_time')
                    ->label('Start Time')
                    ->live()
                    ->after(now())
                    ->before('end_time')
                    ->required(),
                
                DateTimePicker::make('end_time')
                    ->label('End Time')
                    ->live()
                    ->required()
                    ->after('start_time')
                    ->after(now())
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $startTime = $get('start_time');
                
                        // Check for overlapping bookings
                        if ($startTime && $get('type') && $state) {
                            $query = Booking::query()->where('status', '!=', BookingStatusEnum::REJECTED);
                
                            // Filter based on resource type
                            if ($get('type') === 'room') {
                                $query->where('room_id', $get('room_id'));
                            } else {
                                $query->where('server_id', $get('server_id'));
                            }
                
                            // Find overlapping bookings
                            $query->where(function ($q) use ($startTime, $state) {
                                $q->whereBetween('start_time', [$startTime, $state])
                                    ->orWhereBetween('end_time', [$startTime, $state])
                                    ->orWhere(function ($q) use ($startTime, $state) {
                                        $q->where('start_time', '<=', $startTime)
                                          ->where('end_time', '>=', $state);
                                    });
                            });
                
                            if ($query->exists()) {
                                $resourceType = ucfirst($get('type'));
                                Notification::make()
                                    ->danger()
                                    ->title('Booking Conflict')
                                    ->body("This {$resourceType} is already booked during the selected time period.")
                                    ->persistent()
                                    ->send();
                                $set('end_time', null);
                            }
                        }
                    }),
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
                TextColumn::make('user.name')
                    ->label('User')
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
}
