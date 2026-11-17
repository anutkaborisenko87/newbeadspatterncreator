<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->requiresConfirmation(),
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
        ];
    }
}
