<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWidgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:chart,metric,table,map',
            'title' => 'required|string|max:255',
            'data_source' => 'required|string|in:sales,attendance,revenue,tickets',
            'config' => 'nullable|array',
            'position' => 'nullable|array',
            'refresh_interval' => 'nullable|string|in:1m,5m,15m,30m,1h',
        ];
    }
}
