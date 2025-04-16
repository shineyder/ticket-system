<?php

namespace App\Application\UseCases\Queries\GetAllTickets;

class GetAllTicketsQuery
{
    public const ALLOWED_SORT_FIELDS = [
        'created_at',
        'updated_at',
        'title',
        'priority',
        'status'
    ];
    public function __construct(
        public readonly string $orderBy,
        public readonly string $orderDirection
    ) {}
}
