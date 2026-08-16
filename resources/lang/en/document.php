<?php

return [
    'name' => [
        'string' => 'The name must be a string.',
        'max' => 'The name may not be greater than 255 characters.',
    ],
    'description' => [
        'string' => 'The description must be a string.',
    ],
    'keywords' => [
        'array' => 'The keywords value must be an array.',
    ],
    'metadata' => [
        'array' => 'The metadata value must be an array.',
    ],
    'file' => [
        'required' => 'A file is required.',
        'file' => 'The file must be a valid uploaded file.',
        'mimes' => 'The file must be one of the following types: :values.',
    ],
    'errors' => [
        'not_found' => 'No document found for identifier: :identifier',
    ],
    'messages' => [
        'created' => 'Document created successfully.',
        'revised' => 'Document revised successfully.',
        'updated' => 'Document updated successfully.',
        'deleted' => 'Document deleted successfully.',
    ],
];
