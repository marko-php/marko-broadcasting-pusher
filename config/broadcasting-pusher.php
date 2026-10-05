<?php

declare(strict_types=1);

return [
    'app_id' => $_ENV['PUSHER_APP_ID'] ?? '',
    'key' => $_ENV['PUSHER_APP_KEY'] ?? '',
    'secret' => $_ENV['PUSHER_APP_SECRET'] ?? '',
    // Hosted Pusher cluster; used to derive the API host (api-{cluster}.pusher.com) when host is empty.
    'cluster' => $_ENV['PUSHER_APP_CLUSTER'] ?? 'mt1',
    // Set for self-hosted Pusher-protocol servers (Soketi, Laravel Reverb), e.g. 127.0.0.1.
    'host' => $_ENV['PUSHER_HOST'] ?? '',
    'port' => (int) ($_ENV['PUSHER_PORT'] ?? 443),
    'scheme' => $_ENV['PUSHER_SCHEME'] ?? 'https',
    // Publish request timeout, in seconds.
    'timeout' => 5,
];
