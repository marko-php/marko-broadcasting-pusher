<?php

declare(strict_types=1);

use Marko\Authentication\AuthenticatableInterface;
use Marko\Broadcasting\ChannelRegistry;
use Marko\Broadcasting\Pusher\Auth\PusherSignature;
use Marko\Broadcasting\Pusher\Controller\PusherAuthController;
use Marko\Broadcasting\Pusher\Exceptions\PusherException;
use Marko\Broadcasting\Pusher\PusherConfig;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeGuard;

function pusherAuthController(?AuthenticatableInterface $user = null): PusherAuthController
{
    /** @noinspection PhpMissingParentConstructorInspection - Test stub replaces discovery-backed authorization */
    $channelRegistry = new class () extends ChannelRegistry
    {
        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct() {}

        public function authorize(
            string $channelName,
            ?AuthenticatableInterface $user,
        ): bool {
            return $channelName === 'foobar' && $user !== null;
        }
    };

    $guard = new FakeGuard();
    $guard->setUser($user);

    return new PusherAuthController(
        pusherSignature: new PusherSignature(new PusherConfig(
            appId: '3',
            key: '278d425bdf160c739803',
            secret: '7ad3773142a6692b25b8',
        )),
        channelRegistry: $channelRegistry,
        guard: $guard,
    );
}

/**
 * @param array<string, string> $post
 */
function pusherAuthRequest(array $post): Request
{
    return new Request(server: ['REQUEST_METHOD' => 'POST'], post: $post);
}

describe('PusherAuthController', function (): void {
    it('returns key and signature for an authorized user', function (): void {
        $response = pusherAuthController(new FakeAuthenticatable())->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'private-foobar']),
        );

        expect($response->statusCode())->toBe(200)
            ->and($response->headers()['Content-Type'])->toBe('application/json')
            ->and(json_decode($response->body(), true))->toBe([
                'auth' => '278d425bdf160c739803:58df8b0c36d6982b82c3ecf6b4662e34fe8c25bba48f5369f135bf843651c3a4',
            ]);
    });

    it('returns 403 when the registry denies the channel', function (): void {
        $response = pusherAuthController()->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'private-foobar']),
        );

        expect($response->statusCode())->toBe(403)
            ->and($response->body())->not->toContain('auth":');
    });

    it('returns 400 for a missing or malformed socket id', function (): void {
        $controller = pusherAuthController(new FakeAuthenticatable());

        $missing = $controller->authorize(pusherAuthRequest(['channel_name' => 'private-foobar']));
        $malformed = $controller->authorize(
            pusherAuthRequest(['socket_id' => '1234:1234', 'channel_name' => 'private-foobar']),
        );

        expect($missing->statusCode())->toBe(400)
            ->and($malformed->statusCode())->toBe(400);
    });

    it('returns 400 for a non-private channel', function (): void {
        $response = pusherAuthController(new FakeAuthenticatable())->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'foobar']),
        );

        expect($response->statusCode())->toBe(400);
    });

    it('throws for presence channels', function (): void {
        expect(fn () => pusherAuthController(new FakeAuthenticatable())->authorize(
            pusherAuthRequest(['socket_id' => '1234.1234', 'channel_name' => 'presence-room.1']),
        ))->toThrow(PusherException::class, 'Presence channels are not supported');
    });

    it('registers the route at POST /broadcasting/auth', function (): void {
        $attributes = new ReflectionMethod(PusherAuthController::class, 'authorize')->getAttributes(Post::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->newInstance()->path)->toBe('/broadcasting/auth');
    });
});
