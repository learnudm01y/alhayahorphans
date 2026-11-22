<?php

return [
    'connection' => 'database',
    'queue' => 'documents',
    'python_binary' => env('DOCUMENT_ENHANCEMENT_PYTHON', 'python3'),
    'tesseract_binary' => env('DOCUMENT_ENHANCEMENT_TESSERACT', 'tesseract'),
];
