<?php

namespace App\Domain\Entities;

use App\Domain\Events\DomainEvent;
use App\Domain\ValueObjects\Status;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Domain\Exceptions\InvalidTicketStateException;
use App\Domain\ValueObjects\Priority;

class Ticket {
    private array $domainEvents = []; // Array para armazenar eventos

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
        public Priority $priority,
        public Status $status
    ) {
        $this->recordEvent(new TicketCreated(
            $this->id,
            $this->title,
            $this->description,
            $this->priority->value()
        ));
    }

    public function resolve(): void {
        // Só pode resolver se estiver 'open'
        if ($this->status->value() !== Status::OPEN) {
            // Lançar uma exceção de domínio é apropriado aqui
            throw new InvalidTicketStateException("O ticket {$this->id} já está resolvido ou foi fechado.");
        }
        $this->status = new Status(Status::RESOLVED);

        // Dispara evento de domínio
        $this->recordEvent(new TicketResolved($this->id));
    }

    // Método para registrar um evento
    private function recordEvent(DomainEvent $event): void {
        $this->domainEvents[] = $event;
    }

    // Método público para obter os eventos registrados (e talvez limpá-los)
    public function releaseEvents(): array {
        $events = $this->domainEvents;
        $this->domainEvents = []; // Limpa após liberar
        return $events;
    }
}
