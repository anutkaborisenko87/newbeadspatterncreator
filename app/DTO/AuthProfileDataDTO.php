<?php

namespace App\DTO;

use App\Models\User;

final readonly class AuthProfileDataDTO
{
    public function __construct(
        public int $id,
        public string $login,
        public string $email,
        public ?string $firstName,
        public ?string $lastName,
    ) {}

    public function toArray(): array
    {
        return [
                'id' => $this->id,
                'login' => $this->login,
                'email' => $this->email,
                'firstName' => $this->firstName,
                'lastName' => $this->lastName,
            ];
    }

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            login: $user->login,
            email: $user->email,
            firstName:$user->firstName,
            lastName: $user->lastName
        );
    }

}
