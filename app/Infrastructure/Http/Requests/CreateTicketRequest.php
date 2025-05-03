<?php

namespace App\Infrastructure\Http\Requests;

use App\Domain\ValueObjects\Priority;
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
     * Prepare the data for validation.
     *
     * Aqui definimos os valores padrão antes da validação ocorrer.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $defaults = [];

        // Define 'priority' como 'low' se não for enviada ou estiver vazia
        // !filled() para cobrir null, string vazia, etc.
        if (!$this->filled('priority')) {
            $defaults['priority'] = 'low';
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
            'title' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'priority' => [
                'required',
                'string',
                Rule::in(Priority::getAllowedStringValues())
            ]
        ];
    }

    /**
     * Mensagens de erro personalizadas
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'priority.in' => 'O valor fornecido para prioridade é inválido. Valores permitidos são: ' . implode(', ', Priority::getAllowedStringValues()),
        ];
    }

    /**
     * Handle tasks after validation passes.
     *
     * Sanitiza os campos de string para prevenir XSS.
     *
     * @return void
     */
    protected function passedValidation(): void
    {
        $sanitized = [];

        // Sanitiza o título
        $sanitized['title'] = htmlspecialchars($this->input('title'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Sanitiza a descrição, apenas se ela existir (é nullable)
        if ($this->filled('description')) {
            $sanitized['description'] = htmlspecialchars($this->input('description'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $this->merge($sanitized); // Mescla os dados sanitizados de volta na requisição
    }
}
