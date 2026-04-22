<?php

namespace App\Http\Requests\Landlord;

use App\Models\Permission;
use App\Support\Authorization\RbacType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Permission::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = (string) $this->input('type', '');

        return [
            'type' => ['required', 'string', Rule::in(RbacType::all())],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('landlord.permissions', 'name')
                    ->where(static fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->where('type', $type)),
            ],
        ];
    }
}
