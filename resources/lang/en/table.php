<?php

return [
    'search' => [
        'string' => 'The search term must be a string.',
    ],
    'page' => [
        'integer' => 'The page number must be an integer.',
        'min' => 'The page number must be at least 1.',
    ],
    'per_page' => [
        'integer' => 'The per_page value must be an integer.',
        'min' => 'The per_page value must be at least 1.',
        'max' => 'The per_page value may not be greater than 100.',
    ],
    'sort_by' => [
        'string' => 'The sort_by value must be a string.',
    ],
    'sort_order' => [
        'in' => 'The sort_order value must be either "asc" or "desc".',
    ],
    'options' => [
        'array' => 'The options value must be an array.',
    ],
];
