<?php

return [
    'sync_interval_seconds' => (int) env('MIKROTIK_SYNC_INTERVAL', 15),
    'interface_poll_interval_seconds' => (int) env('MIKROTIK_INTERFACE_POLL_INTERVAL', 60),
    'mock' => filter_var(env('MIKROTIK_MOCK', false), FILTER_VALIDATE_BOOL),
];
