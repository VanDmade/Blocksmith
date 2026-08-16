<?php

namespace VanDmade\Blocksmith\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentRequest extends FormRequest
{

    public function messages(): array
    {
        return [
            'name.string' => __('blocksmith::document.name.string'),
            'name.max' => __('blocksmith::document.name.max'),
            'description.string' => __('blocksmith::document.description.string'),
            'keywords.array' => __('blocksmith::document.keywords.array'),
            'metadata.array' => __('blocksmith::document.metadata.array'),
            'file.required' => __('blocksmith::document.file.required'),
            'file.file' => __('blocksmith::document.file.file'),
            'file.mimes' => __('blocksmith::document.file.mimes'),
        ];
    }

    public function rules(): array
    {
        $document = $this->route('document');
        $fileRules = [$document ? 'nullable' : 'required', 'file'];
        $allowedFileTypes = config('blocksmith.allowed_file_types', ['pdf', 'png', 'jpg', 'jpeg', 'gif']);
        if (!empty($allowedFileTypes)) {
            $fileRules[] = 'mimes:'.implode(',', $allowedFileTypes);
        }
        return [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'keywords' => 'nullable|array',
            'metadata' => 'nullable|array',
            'file' => $fileRules,
        ];
    }

}
