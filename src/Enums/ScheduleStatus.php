<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Enums;

enum ScheduleStatus: string
{
    case AVAILABLE = 'available';
    case BOOKED = 'booked';
    case CANCELLED = 'cancelled';
    case BLOCKED = 'blocked';
    case COMPLETED = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Available',
            self::BOOKED => 'Booked',
            self::CANCELLED => 'Cancelled',
            self::BLOCKED => 'Blocked',
            self::COMPLETED => 'Completed'

        };
    }

    public function isAvailable(): bool
    {
        return $this === self::AVAILABLE;
    }

    public function isBooked(): bool
    {
        return $this === self::BOOKED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function isBlocked(): bool
    {
        return $this === self::BLOCKED;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }
}
