<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryBatchCategoryAttachmentsRequest extends FormRequest
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
     * Single source of truth for the batch category attachment file type and
     * size policy. The Livewire batch category manager reuses these exact rules
     * so validation never drifts between the controller and the UI.
     *
     * @return array<string, mixed>
     */
    public static function uploadRules(): array
    {
        return [
            'attachmentFiles' => ['required', 'array'],
            'attachmentFiles.*' => ['file', 'max:10240'], // 10 MB per file; add mimes: here when the type policy is known.
        ];
    }
}
