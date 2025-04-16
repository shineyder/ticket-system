<?php

namespace App\Infrastructure\Http\Requests;

use App\Application\UseCases\Queries\GetAllTickets\GetAllTicketsQuery;
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
     * Prepare the data for validation.
     *
     * Aqui definimos os valores padrão antes da validação ocorrer.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $defaults = [];
        
        // Define 'orderBy' como 'created_at' se não for enviada ou estiver vazia
        // !filled() para cobrir null, string vazia, etc.
        if (!$this->filled('orderBy')) {
            $defaults['orderBy'] = 'created_at';
        }

        // Define 'orderDirection' como 'desc' se não for enviada ou estiver vazia
        // !filled() para cobrir null, string vazia, etc.
        if (!$this->filled('orderDirection')) {
            $defaults['orderDirection'] = 'desc';
        }

        // Mescla os valores padrão de volta na requisição se houver algum
        if (!empty($defaults)) {
            $this->merge($defaults);
        }
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
