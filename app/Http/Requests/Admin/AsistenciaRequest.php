<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsistenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function datos(): array
    {
        $reglas = [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'status' => ['required', Rule::in(['presente', 'ausente'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
        if ($this->routeIs('*.store')) {
            $reglas['user_id'] = ['required', 'integer', 'min:1', Rule::exists('users', 'id')->whereNotNull('usertype_id')];
        }

        return $this->validate($reglas);
    }
}
