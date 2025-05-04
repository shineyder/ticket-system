<?php

namespace App\Infrastructure\Http\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "ValidationError",
    title: "Validation Error",
    description: "Estrutura de resposta para erros de validação retornados pela API (geralmente status 422).",
    properties: [
        new OA\Property(
            property: "message",
            type: "string",
            description: "Mensagem geral indicando que a validação falhou.",
            example: "The given data was invalid."
        ),
        new OA\Property(
            property: "errors",
            type: "object",
            description: "Um objeto onde cada chave é o nome do campo que falhou na validação e o valor é um array de strings contendo as mensagens de erro para aquele campo.",
            additionalProperties: new OA\AdditionalProperties(
                type: "array",
                items: new OA\Items(type: "string")
            ),
            example: [
                "title" => ["The title field is required."],
                "priority" => ["The selected priority is invalid."]
            ]
        )
    ],
    type: "object"
)]
class ValidationError {}
