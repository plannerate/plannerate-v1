<?php

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FetchRepositoryProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->can('create', Product::class) || $user->can('viewAny', Product::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ean' => ['required', 'string', 'max:255'],
        ];
    }
}
