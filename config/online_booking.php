<?php

return [
    'use_publication_resolver' => (bool) env('ONLINE_BOOKING_USE_PUBLICATION_RESOLVER', true),
    'preview_ttl_minutes' => (int) env('ONLINE_BOOKING_PREVIEW_TTL_MINUTES', 30),
];
