<?php

declare(strict_types=1);

use Marko\Broadcasting\BroadcasterInterface;
use Marko\Broadcasting\Pusher\Driver\PusherBroadcaster;
use Marko\Broadcasting\Pusher\PusherConfig;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Testing\Fake\FakeConfigRepository;

describe('broadcasting-pusher module', function (): void {
    it('binds BroadcasterInterface to PusherBroadcaster', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'][BroadcasterInterface::class])->toBe(PusherBroadcaster::class);
    });

    it('builds PusherConfig from config/broadcasting-pusher.php values', function (): void {
        $module = require dirname(__DIR__) . '/module.php';
        $defaults = require dirname(__DIR__) . '/config/broadcasting-pusher.php';

        $values = [];
        foreach ([...$defaults, 'app_id' => '42', 'key' => 'app-key', 'secret' => 'app-secret', 'host' => 'reverb.test', 'port' => 8080, 'scheme' => 'http'] as $key => $value) {
            $values["broadcasting-pusher.$key"] = $value;
        }

        $container = new Container();
        $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository($values));

        $config = $module['bindings'][PusherConfig::class]($container);

        expect($config)->toBeInstanceOf(PusherConfig::class)
            ->and($config->appId)->toBe('42')
            ->and($config->key)->toBe('app-key')
            ->and($config->secret)->toBe('app-secret')
            ->and($config->baseUrl())->toBe('http://reverb.test:8080')
            ->and($config->timeout)->toBe(5);
    });

    it('derives the api host from the cluster when no host is configured', function (): void {
        $config = new PusherConfig(appId: '1', key: 'k', secret: 's', cluster: 'eu');

        expect($config->apiHost())->toBe('api-eu.pusher.com')
            ->and($config->baseUrl())->toBe('https://api-eu.pusher.com:443');
    });

    it('has a valid composer.json requiring the broadcasting, http and config packages', function (): void {
        $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

        expect($composer['name'])->toBe('marko/broadcasting-pusher')
            ->and($composer['type'])->toBe('marko-module')
            ->and($composer)->not->toHaveKey('version')
            ->and($composer['require'])->toHaveKeys(['marko/broadcasting', 'marko/http', 'marko/config'])
            ->and($composer['autoload']['psr-4'])->toBe(['Marko\\Broadcasting\\Pusher\\' => 'src/'])
            ->and($composer['extra']['marko']['module'])->toBeTrue();
    });
});
