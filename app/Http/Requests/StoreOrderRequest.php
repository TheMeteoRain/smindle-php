<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(
            $this->clientRules(),
            $this->contentsRules()
        );
    }

    /**
     * Validation rules for client.
     */
    public static function clientRules(): array
    {
        return [
            'client.identity'      => ['required', 'string', 'max:255'],
            'client.contact_point' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Validation rules for order contents.
     */
    public function contentsRules(): array
    {
        return [
            'contents'                 => ['required', 'array', 'min:1'],
            'contents.*.label'         => ['required', 'string', 'max:255'],
            'contents.*.kind'          => ['required', 'string', 'in:single,recurring'],
            'contents.*.cost'          => ['required', 'numeric', 'min:0'],
            'contents.*.meta'          => ['nullable', 'array'],

            'contents.*.meta.priority' => [
                'sometimes',
                Rule::in(['low', 'normal', 'high', 'critical', 'max']),
            ],
        ];
    }
}
