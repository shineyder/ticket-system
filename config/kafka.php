<?php
return [
    'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),
    'topics' => [
        'tickets' => env('KAFKA_TOPIC', 'tickets_created'),
    ],
];
