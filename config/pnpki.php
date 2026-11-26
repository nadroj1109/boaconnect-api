<?php

return [
    'python_binary' => env(
        'PNPKI_PYTHON_BINARY',
        base_path('.venv/Scripts/python.exe')
    ),

    'script' => base_path('scripts/verify_pnpki_pdf.py'),

    'trust_directory' => storage_path('app/pnpki/trust'),

    'certificates_directory' => storage_path('app/pnpki/certificates'),

    'verification' => [
        'enabled' => env('PNPKI_VERIFICATION_ENABLED', false),
        'timeout' => (int) env('PNPKI_TIMEOUT', 60),
    ],
];
