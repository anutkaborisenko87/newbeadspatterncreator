<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('email')
                    ->label('Email address')
                    ->required()
                    ->email()
                    ->maxLength(255)
                    ->unique(
                        table: 'users',
                        column: 'email',
                        ignorable: fn($record) => $record
                ),
                TextInput::make('login')
                    ->maxLength(255)
                    ->unique(
                        table: 'users',
                        column: 'login',
                        ignorable: fn($record) => $record
                ),
                TextInput::make('firstName')
                    ->label('First name')
                    ->maxLength(30),
                TextInput::make('lastName')
                    ->label('Last name')
                    ->maxLength(30),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->rules([
                        'required',
                        'string',
                        'min:8',
                        'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/',
                    ])
                    ->validationMessages([
                        'required' => 'Password is required.',
                        'min' => 'Password must contain at least :min characters.',
                        'regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
                    ])
                    ->suffixAction(
                        Action::make('generate')
                            ->label('Generate')
                            ->icon('heroicon-m-key')
                            ->action(function ($state, $set) {
                                $set('password', self::generateSecurePassword());
                            })
                    )
                    ->dehydrated(fn($state) => filled($state))
                    ->visible(fn(string $operation) => $operation === 'create')
                    ->required(fn(string $operation) => $operation === 'create')
                    ->dehydrated(fn(string $operation, $state) => $operation === 'create' && filled($state))
            ]);
    }

    public static function generateSecurePassword($length = 12): string
    {
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';
        $special = '!@#$%^&*()-_=+[]{};:,.<>/?|\\~`' . '_';

        $password = $lower[random_int(0, strlen($lower) - 1)];
        $password .= $upper[random_int(0, strlen($upper) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        $all = $lower . $upper . $digits . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }
        return str_shuffle($password);
    }
}
