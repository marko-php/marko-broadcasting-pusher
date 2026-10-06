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
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

/**
 * Private- and presence-channel authorization endpoint used by pusher-js / Laravel Echo.
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
     * @throws HttpException|PusherException|ChannelAuthorizationException|JsonException|ReflectionException|ContainerExceptionInterface|NotFoundExceptionInterface
     */
    #[Post('/broadcasting/auth')]
    public function authorize(
        Request $request,
    ): Response {
        $socketId = $request->post('socket_id');
        $channelName = $request->post('channel_name');

        if (!is_string($socketId) || preg_match(self::SOCKET_ID_PATTERN, $socketId) !== 1) {
            throw HttpException::badRequest('A valid socket_id is required.');
        }

        if (!is_string($channelName) || $channelName === '') {
            throw HttpException::badRequest('A channel_name is required.');
        }

        if (str_starts_with($channelName, self::PRESENCE_PREFIX)) {
            return $this->authorizePresence($socketId, $channelName);
        }

        if (!str_starts_with($channelName, self::PRIVATE_PREFIX)) {
            throw HttpException::badRequest('Only private and presence channels require authorization.');
        }

        $name = substr($channelName, strlen(self::PRIVATE_PREFIX));

        if (!$this->channelRegistry->authorize($name, $this->guard->user())) {
            throw HttpException::forbidden('Forbidden.');
        }

        return Response::json(['auth' => $this->pusherSignature->channelAuth($socketId, $channelName)]);
    }

    /**
     * @throws HttpException|PusherException|ChannelAuthorizationException|JsonException|ReflectionException|ContainerExceptionInterface|NotFoundExceptionInterface
     */
    private function authorizePresence(
        string $socketId,
        string $channelName,
    ): Response {
        $name = substr($channelName, strlen(self::PRESENCE_PREFIX));
        $member = $this->channelRegistry->authorizePresence($name, $this->guard->user());

        if ($member === null) {
            throw HttpException::forbidden('Forbidden.');
        }

        $channelData = ['user_id' => (string) $member->id];

        if ($member->info !== []) {
            $channelData['user_info'] = $member->info;
        }

        $encoded = json_encode($channelData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return Response::json([
            'auth' => $this->pusherSignature->presenceChannelAuth($socketId, $channelName, $encoded),
            'channel_data' => $encoded,
        ]);
    }
}
