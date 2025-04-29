<?php

namespace App\Domain\ValueObjects;

class Priority {
    public const LOW = 0;
    public const MEDIUM = 1;
    public const HIGH = 2;

    private int $value;

    public function __construct(int $value) {
        if (!in_array($value, [self::LOW, self::MEDIUM, self::HIGH])) {
            throw new \InvalidArgumentException("Prioridade inválida: " . $value);
        }
        $this->value = $value;
    }

    public function value(): int {
        return $this->value;
    }

    public static function fromString(string $priority): self
    {
        $priorities = [
            'low' => self::LOW,
            'medium' => self::MEDIUM,
            'high' => self::HIGH,
        ];

        if (!isset($priorities[$priority])) {
            throw new \InvalidArgumentException("Prioridade inválida: " . $priority);
        }

        return new self($priorities[$priority]);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value();
    }

    public static function getAllowedStringValues(): array
    {
        // Retorna as chaves do array usado em fromString
        return ['low', 'medium', 'high'];
    }

    /**
     * Retorna a representação em string da prioridade.
     *
     * @return string
     */
    public function toString(): string
    {
        return match ($this->value) {
            self::LOW => 'low',
            self::MEDIUM => 'medium',
            self::HIGH => 'high',
        };
    }
}
