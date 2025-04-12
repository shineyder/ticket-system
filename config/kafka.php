<?php
return [
    'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),
    'topics' => [
        'ticket-events' => [ // Nome do alias usado no Listener
            'topic' => env('KAFKA_TICKET_EVENTS_TOPIC', 'ticket-events'), // Nome real do tópico no Kafka
            'broker' => env('KAFKA_BROKERS', 'kafka:9092'), // Broker para este tópico
        ],
    ],
];
