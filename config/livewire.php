<?php

return [
    'payload' => [
        'max_size' => 1024 * 1024,
        // Rich-text fields in section modals send nested document paths.
        'max_nesting_depth' => 32,
        'max_calls' => 50,
        'max_components' => 200,
    ],
];
