<?php

namespace App\Domain\ValueObjects;

class Status {
    public const OPEN = 'open';
    public const RESOLVED = 'resolved';

    private string $value;

    public function __construct(string $value) {
        if (!in_array($value, [self::OPEN, self::RESOLVED])) {
            throw new \InvalidArgumentException("Status inválido: " . $value);
        }
        $this->value = $value;
    }

    public function value(): string {
        return $this->value;
    }


    public function equals(self $other): bool
    {
        return $this->value === $other->value();
    }

    public static function getAllowedValues(): array
    {
        return [self::OPEN, self::RESOLVED];
    }
}
