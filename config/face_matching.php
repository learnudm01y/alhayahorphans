<?php

return [
    'model_version' => env('FACE_MATCH_MODEL_VERSION', 'face-api-resnet34-128d-v1'),
    'node_binary' => env('FACE_MATCH_NODE_BINARY', 'node'),
    'queue' => 'faces',

    'auto_threshold' => (float) env('FACE_MATCH_AUTO_THRESHOLD', 0.44),
    'review_threshold' => (float) env('FACE_MATCH_REVIEW_THRESHOLD', 0.55),

    'process' => [
        'timeout_seconds' => (int) env('FACE_MATCH_TIMEOUT_SECONDS', 60),
        'budget_ms' => (int) env('FACE_MATCH_BUDGET_MS', 12000),
        'max_side' => (int) env('FACE_MATCH_MAX_SIDE', 1600),
        'min_score' => (float) env('FACE_MATCH_MIN_SCORE', 0.50),
        'min_face_width' => (int) env('FACE_MATCH_MIN_FACE_WIDTH', 60),
        'dominance_ratio' => (float) env('FACE_MATCH_DOMINANCE_RATIO', 1.50),
    ],

    'calibration_sample_size' => (int) env('FACE_MATCH_CALIBRATION_SAMPLE_SIZE', 100),
    'result_ttl_days' => (int) env('FACE_MATCH_RESULT_TTL_DAYS', 7),
    'images_per_run' => (int) env('FACE_MATCH_IMAGES_PER_RUN', 5),

    // الحذف التلقائي للمطابق المؤكد معطل افتراضياً حتى اعتماد تقرير المعايرة، ويعمل عبر سلة استرجاع فقط.
    'deletion_enabled' => filter_var(env('FACE_MATCH_DELETION_ENABLED', false), FILTER_VALIDATE_BOOL),
    'trash_retention_days' => (int) env('FACE_MATCH_TRASH_RETENTION_DAYS', 30),
];
