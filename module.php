<?php

declare(strict_types=1);

use Marko\Broadcasting\BroadcasterInterface;
use Marko\Broadcasting\Pusher\Driver\PusherBroadcaster;
use Marko\Broadcasting\Pusher\PusherConfig;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;

return [
    'bindings' => [
        BroadcasterInterface::class => PusherBroadcaster::class,
        PusherConfig::class => static function (ContainerInterface $container): PusherConfig {
            $config = $container->get(ConfigRepositoryInterface::class);

            return new PusherConfig(
                appId: $config->getString(key: 'broadcasting-pusher.app_id'),
                key: $config->getString(key: 'broadcasting-pusher.key'),
                secret: $config->getString(key: 'broadcasting-pusher.secret'),
                cluster: $config->getString(key: 'broadcasting-pusher.cluster'),
                host: $config->getString(key: 'broadcasting-pusher.host'),
                port: $config->getInt(key: 'broadcasting-pusher.port'),
                scheme: $config->getString(key: 'broadcasting-pusher.scheme'),
                timeout: $config->getInt(key: 'broadcasting-pusher.timeout'),
            );
        },
    ],
    'singletons' => [
        PusherConfig::class,
    ],
];
