<?php

return [
    'ssr' => [
        'enabled' => false,
    ],

    // Vue pages live in lower-case resources/js/pages (Linux is case-sensitive).
    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/pages')],
        'page_extensions' => ['vue'],
    ],
];
