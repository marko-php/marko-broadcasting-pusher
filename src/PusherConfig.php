<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Pusher;

/**
 * Pusher driver settings, built from config/broadcasting-pusher.php by the module.php binding.
 */
readonly class PusherConfig
{
    public function __construct(
        public string $appId,
        public string $key,
        public string $secret,
        public string $cluster = 'mt1',
        public string $host = '',
        public int $port = 443,
        public string $scheme = 'https',
        public int $timeout = 5,
    ) {}

    public function apiHost(): string
    {
        return $this->host !== '' ? $this->host : "api-$this->cluster.pusher.com";
    }

    public function baseUrl(): string
    {
        return "$this->scheme://{$this->apiHost()}:$this->port";
    }
}
