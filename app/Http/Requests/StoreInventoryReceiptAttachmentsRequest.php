<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryReceiptAttachmentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()?->can('inventory.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::uploadRules();
    }

    /**
     * Shared upload rules.
     *
     * Single source of truth for the receipt file type and size policy. The
     * Livewire inventory profile reuses these exact rules so validation never
     * drifts between the controller and the UI.
     *
     * @return array<string, mixed>
     */
    public static function uploadRules(): array
    {
        return [
            'receiptFiles' => ['required', 'array'],
            'receiptFiles.*' => ['file', 'max:10240'], // 10 MB per file; add mimes: here when the type policy is known.
        ];
    }
}
