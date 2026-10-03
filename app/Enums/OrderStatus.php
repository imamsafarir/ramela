<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Processed = 'processed';
    case ReadyToShip = 'ready_to_ship';
    case Shipping = 'shipping';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Transisi yang sah. Status baru cukup ditambah di sini. */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::Processed, self::Cancelled],
            self::Processed => [self::ReadyToShip, self::Cancelled],
            self::ReadyToShip => [self::Shipping, self::Cancelled],
            self::Shipping => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Paid => 'Dibayar',
            self::Processed => 'Diproses',
            self::ReadyToShip => 'Siap Dikirim',
            self::Shipping => 'Dikirim',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
