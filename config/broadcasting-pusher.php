<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    'app_id' => Env::string('PUSHER_APP_ID', ''),
    'key' => Env::string('PUSHER_APP_KEY', ''),
    'secret' => Env::string('PUSHER_APP_SECRET', ''),
    // Hosted Pusher cluster; used to derive the API host (api-{cluster}.pusher.com) when host is empty.
    'cluster' => Env::string('PUSHER_APP_CLUSTER', 'mt1'),
    // Set for self-hosted Pusher-protocol servers (Soketi, Laravel Reverb), e.g. 127.0.0.1.
    'host' => Env::string('PUSHER_HOST', ''),
    'port' => Env::int('PUSHER_PORT', 443, min: 1, max: 65535),
    'scheme' => Env::string('PUSHER_SCHEME', 'https'),
    // Publish request timeout, in seconds.
    'timeout' => 5,
];
