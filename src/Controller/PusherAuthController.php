<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Pusher\Controller;

use JsonException;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Broadcasting\ChannelRegistry;
use Marko\Broadcasting\Exceptions\ChannelAuthorizationException;
use Marko\Broadcasting\Pusher\Auth\PusherSignature;
use Marko\Broadcasting\Pusher\Exceptions\PusherException;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

/**
 * Private-channel authorization endpoint used by pusher-js / Laravel Echo.
 *
 * Replace it with #[Preference] on a subclass to change the path or add middleware.
 */
readonly class PusherAuthController
{
    private const string SOCKET_ID_PATTERN = '/^\d+\.\d+$/';

    private const string PRIVATE_PREFIX = 'private-';

    private const string PRESENCE_PREFIX = 'presence-';

    public function __construct(
        private PusherSignature $pusherSignature,
        private ChannelRegistry $channelRegistry,
        private GuardInterface $guard,
    ) {}

    /**
     * @throws PusherException|ChannelAuthorizationException|JsonException|ReflectionException|ContainerExceptionInterface|NotFoundExceptionInterface
     */
    #[Post('/broadcasting/auth')]
    public function authorize(
        Request $request,
    ): Response {
        $socketId = $request->post('socket_id');
        $channelName = $request->post('channel_name');

        if (!is_string($socketId) || preg_match(self::SOCKET_ID_PATTERN, $socketId) !== 1) {
            return Response::json(['error' => 'A valid socket_id is required.'], 400);
        }

        if (!is_string($channelName) || $channelName === '') {
            return Response::json(['error' => 'A channel_name is required.'], 400);
        }

        if (str_starts_with($channelName, self::PRESENCE_PREFIX)) {
            throw PusherException::presenceChannelsNotSupported($channelName);
        }

        if (!str_starts_with($channelName, self::PRIVATE_PREFIX)) {
            return Response::json(['error' => 'Only private channels require authorization.'], 400);
        }

        $name = substr($channelName, strlen(self::PRIVATE_PREFIX));

        if (!$this->channelRegistry->authorize($name, $this->guard->user())) {
            return Response::json(['error' => 'Forbidden.'], 403);
        }

        return Response::json(['auth' => $this->pusherSignature->channelAuth($socketId, $channelName)]);
    }
}
