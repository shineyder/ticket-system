<?php

namespace App\Infrastructure\Http\Requests;

use App\Application\Query\GetAllTickets\GetAllTicketsQuery;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class GetAllTicketsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Por enquanto, qualquer um pode tentar listar todos os tickets.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'orderBy' => Rule::in(GetAllTicketsQuery::ALLOWED_SORT_FIELDS),
            'orderDirection' => 'in:asc,desc',
        ];
    }
}
