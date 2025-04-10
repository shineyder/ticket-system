<?php

namespace App\Application\Query\GetAllTickets;

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
        public readonly string $orderBy = 'created_at',
        public readonly string $orderDirection = 'desc'
    ) {}
}
