<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Processed = 'processed';
    case ReadyToShip = 'ready_to_ship';
    case ReadyForPickup = 'ready_for_pickup';
    case Shipping = 'shipping';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Transisi yang sah. Disesuaikan dengan metode pengiriman (pickup vs kurir). */
    public function allowedNext(?string $deliveryType = null): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::Processed, self::Cancelled],
            self::Processed => match ($deliveryType) {
                'pickup' => [self::ReadyForPickup, self::Cancelled],
                'courier' => [self::ReadyToShip, self::Cancelled],
                default => [self::ReadyToShip, self::ReadyForPickup, self::Cancelled],
            },
            self::ReadyToShip => [self::Shipping, self::Completed, self::Cancelled],
            self::ReadyForPickup => [self::Completed, self::Cancelled],
            self::Shipping => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next, ?string $deliveryType = null): bool
    {
        return in_array($next, $this->allowedNext($deliveryType), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Paid => 'Dibayar',
            self::Processed => 'Diproses',
            self::ReadyToShip => 'Siap Dikirim',
            self::ReadyForPickup => 'Siap Dijemput',
            self::Shipping => 'Dikirim',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
