<?php

namespace App\Infrastructure\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Por padrão, retorno true. Em uma aplicação real posso
     * verificar aqui se o usuário autenticado tem permissão para criar tickets.
     * Exemplo: return auth()->check() && auth()->user()->can('create', Ticket::class);
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Por enquanto, qualquer um pode tentar criar um ticket.
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
            'title' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'priority' => ['nullable', 'string', Rule::in(Priority::getAllowedStringValues())]
        ];
    }
}
