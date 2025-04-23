<?php

namespace App\Domain\Entities;

use App\Domain\Events\DomainEvent;
use App\Domain\ValueObjects\Status;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Domain\Exceptions\InvalidTicketStateException;
use App\Domain\ValueObjects\Priority;
use DateTimeImmutable;

/**
 * Representa o Agregado Raiz Ticket no contexto de Event Sourcing.
 * O estado interno é construído aplicando eventos.
 */
class Ticket {
    private string $id;
    private ?string $title = null;
    private ?string $description = null;
    private ?Priority $priority = null;
    private ?Status $status = null;
    private ?DateTimeImmutable $createdAt = null;
    private ?DateTimeImmutable $resolvedAt = null;

    /**
     * @var DomainEvent[] Lista de eventos que ainda não foram persistidos.
     */
    private array $uncommittedEvents = [];

    // Construtor privado para ser usado internamente e na reconstituição.
    private function __construct(string $id) {
        $this->id = $id;
    }

    /**
     * Método fábrica estático para criar um novo Ticket.
     * Gera o evento TicketCreated e aplica-o para definir o estado inicial.
     */
    public static function create(string $id, string $title, string $description, string $priority): self {
        $ticket = new self($id); // Cria instância apenas com ID

        $priorityValueObject = Priority::fromString($priority); // Converte string para VO
        $priorityIntValue = $priorityValueObject->value(); // Pega o valor int

        // Cria o evento com os dados iniciais
        $event = new TicketCreated($id, $title, $description, $priorityIntValue);

        // Grava e aplica o evento para definir o estado inicial
        $ticket->recordAndApplyEvent($event);

        return $ticket;
    }

    /**
     * Marca o ticket como resolvido.
     * Valida a regra de negócio e gera o evento TicketResolved.
     *
     * @throws InvalidTicketStateException Se o ticket não estiver no estado OPEN.
     */
    public function resolve(): void {
        // Validação da regra de negócio (estado atual)
        if (!$this->status || $this->status->value() !== Status::OPEN) {
            throw new InvalidTicketStateException("O ticket {$this->id} não pode ser resolvido pois não está aberto.");
        }

        // Cria o evento que representa a resolução
        $event = new TicketResolved($this->id);

        // Grava e aplica o evento para mudar o estado
        $this->recordAndApplyEvent($event);
    }


    // --- Métodos de Aplicação de Eventos ---

    /**
     * Aplica um evento ao estado atual do agregado.
     * Direciona para o método apply<EventName> específico.
     */
    private function apply(DomainEvent $event): void {
        $method = $this->getApplyMethodName($event);
        if (method_exists($this, $method)) {
            $this->{$method}($event);
        }
    }

    /**
     * Aplica o estado inicial quando um TicketCreated ocorre.
     * @internal Este método é chamado dinamicamente pelo método apply() via getApplyMethodName().
     */
    private function applyTicketCreated(TicketCreated $event): void {// NOSONAR
        // Define o estado interno com base nos dados do evento
        $this->title = $event->title;
        $this->description = $event->description;
        $this->priority = new Priority($event->priority);
        $this->status = new Status(Status::OPEN); // Estado inicial padrão
        $this->createdAt = $event->getOccurredOn();
    }

    /**
     * Aplica a mudança de estado quando um TicketResolved ocorre.
     * @internal Este método é chamado dinamicamente pelo método apply() via getApplyMethodName().
     */
    private function applyTicketResolved(TicketResolved $event): void {// NOSONAR
        // Muda o estado interno
        $this->status = new Status(Status::RESOLVED);
        $this->resolvedAt = $event->getOccurredOn();
    }
    // --- Métodos Auxiliares e de Infraestrutura ES ---

    /**
     * Grava um novo evento na lista de não commitados E aplica-o ao estado atual.
     */
    private function recordAndApplyEvent(DomainEvent $event): void {
        $this->uncommittedEvents[] = $event;
        $this->apply($event); // Aplica imediatamente para manter o estado consistente
    }

    /**
     * Reconstrói o agregado a partir de um histórico de eventos.
     * Usado pelo Event Store ao carregar o agregado.
     *
     * @param DomainEvent[] $events Histórico de eventos para este agregado.
     */
    public static function reconstituteFromHistory(string $id, array $events): self {
        $ticket = new self($id); // Cria instância vazia (apenas com ID)
        foreach ($events as $event) {
            $ticket->apply($event); // Aplica cada evento histórico SEM gravá-lo novamente
        }
        return $ticket;
    }

    /**
     * Retorna e limpa a lista de eventos não commitados.
     * Usado pelo Event Store após salvar os eventos.
     *
     * @return DomainEvent[]
     */
    public function pullUncommittedEvents(): array {
        $events = $this->uncommittedEvents;
        $this->uncommittedEvents = []; // Limpa a lista
        return $events;
    }

    /**
     * Gera o nome do método apply baseado no nome da classe do evento.
     * Ex: TicketCreated -> applyTicketCreated
     */
    private function getApplyMethodName(DomainEvent $event): string {
        $classParts = explode('\\', get_class($event));
        return 'apply' . end($classParts);
    }

    public function getId(): string {
        return $this->id;
    }
    public function getTitle(): ?string {
        return $this->title;
    }
    public function getDescription(): ?string {
        return $this->description;
    }
    public function getPriority(): ?Priority {
        return $this->priority;
    }
    public function getStatus(): ?Status {
        return $this->status;
    }
    public function getCreatedAt(): ?DateTimeImmutable {
        return $this->createdAt;
    }
    public function getResolvedAt(): ?DateTimeImmutable {
        return $this->resolvedAt;
    }
}
