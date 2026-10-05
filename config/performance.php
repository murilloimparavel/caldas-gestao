<?php

return [
    'http_metrics_enabled' => filter_var(env('PERF_HTTP_METRICS', false), FILTER_VALIDATE_BOOL),
    'credentials_file' => env('P0_CREDENTIALS_FILE', sys_get_temp_dir().'/caldas-p0-api-credentials.json'),
    'artifact_path' => env('P0_ARTIFACT_PATH', 'storage/app/p0-api-baseline.json'),
];
