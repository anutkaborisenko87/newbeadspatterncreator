<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): void {
                $currentUserId = Filament::auth()->id();

                if ($currentUserId !== null) {
                    $query->whereKeyNot($currentUserId);
                }
            })
            ->columns([
                ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->circular()
                    ->defaultImageUrl(null),

                TextColumn::make('login')
                    ->label('Login')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('firstName')
                    ->label('First name')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('lastName')
                    ->label('Last name')
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('blocked')
                    ->label('User status')
                    ->placeholder('All users')
                    ->trueLabel('Blocked')
                    ->falseLabel('Active')
                    ->queries(
                        true: fn (Builder $query): Builder =>
                        $query->where('blocked', true),

                        false: fn (Builder $query): Builder =>
                        $query->where('blocked', false),

                        blank: fn (Builder $query): Builder => $query,
                    ),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('toggleBlock')
                        ->label(fn (User $record): string =>
                        $record->blocked ? 'Unblock user' : 'Block user'
                        )
                        ->icon(fn (User $record): string =>
                        $record->blocked
                            ? 'heroicon-o-lock-open'
                            : 'heroicon-o-lock-closed'
                        )
                        ->color(fn (User $record): string =>
                        $record->blocked ? 'success' : 'danger'
                        )
                        ->requiresConfirmation()
                        ->modalHeading(fn (User $record): string =>
                        $record->blocked
                            ? 'Unblock this user?'
                            : 'Block this user?'
                        )
                        ->action(function (Action $action, User $record): void {
                            $record->update([
                                'blocked' => ! $record->blocked,
                            ]);

                            $action->success();
                        })
                        ->successNotificationTitle('User status updated'),
                    DeleteAction::make()->requiresConfirmation(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('blockUsers')
                        ->label('Block selected')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Block selected users?')
                        ->action(function (BulkAction $action, Collection $records): void {
                            $records->each(
                                fn (User $user) => $user->update(['blocked' => true])
                            );

                            $action->success();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Selected users blocked'),

                    BulkAction::make('unblockUsers')
                        ->label('Unblock selected')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Unblock selected users?')
                        ->action(function (BulkAction $action, Collection $records): void {
                            $records->each(
                                fn (User $user) => $user->update(['blocked' => false])
                            );

                            $action->success();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Selected users unblocked'),

                    DeleteBulkAction::make()->requiresConfirmation(),
                ]),
            ]);
    }
}
