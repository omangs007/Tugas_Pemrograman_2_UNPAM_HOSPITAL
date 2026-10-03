<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ActorType;
use App\Enums\BookingChannel;
use App\Models\User;
use InvalidArgumentException;

/**
 * Pelaku sebuah operasi (11 §6.1). Service tidak membaca request(): pelaku selalu dikirim eksplisit.
 */
final readonly class Actor
{
    private function __construct(public ActorType $type, public ?int $userId) {}

    public static function patient(User $user): self
    {
        return new self(ActorType::Patient, self::persistedId($user));
    }

    public static function staff(User $user): self
    {
        return new self(ActorType::Staff, self::persistedId($user));
    }

    /** Pelaku otomatis/perintah konsol: tanpa pengguna. */
    public static function system(): self
    {
        return new self(ActorType::System, null);
    }

    public function isPatient(): bool
    {
        return $this->type === ActorType::Patient;
    }

    public function isStaff(): bool
    {
        return $this->type === ActorType::Staff;
    }

    public function isSystem(): bool
    {
        return $this->type === ActorType::System;
    }

    /** Akun pelaku, atau NULL untuk pelaku sistem / akun yang barisnya sudah tidak ada. */
    public function user(): ?User
    {
        return $this->userId === null ? null : User::query()->find($this->userId);
    }

    /** Kanal pendaftaran menurut pelaku: staf ⇒ loket, selain itu online. */
    public function channel(): BookingChannel
    {
        return $this->isStaff() ? BookingChannel::Loket : BookingChannel::Online;
    }

    private static function persistedId(User $user): int
    {
        if (! $user->exists) {
            throw new InvalidArgumentException('Pelaku harus berupa akun yang sudah tersimpan.');
        }

        return (int) $user->getKey();
    }
}
