<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Pusher\Driver;

use JsonException;
use Marko\Broadcasting\BroadcastableInterface;
use Marko\Broadcasting\BroadcasterInterface;
use Marko\Broadcasting\Channel;
use Marko\Broadcasting\Exceptions\BroadcastException;
use Marko\Broadcasting\Pusher\Auth\PusherSignature;
use Marko\Broadcasting\Pusher\Exceptions\PusherException;
use Marko\Broadcasting\Pusher\PusherConfig;
use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\Exceptions\HttpException;

/**
 * Triggers events through the Pusher HTTP API. Works with hosted Pusher and with any server that
 * speaks the Pusher protocol (Soketi, Laravel Reverb); the server holds the WebSocket connections.
 *
 * The Pusher protocol has no event ids, so the `$id` argument is not transmitted.
 */
readonly class PusherBroadcaster implements BroadcasterInterface
{
    private const string DRIVER = 'Pusher';

    private const string CHANNEL_NAME_PATTERN = '/^[A-Za-z0-9_\-=@,.;]{1,164}$/';

    public function __construct(
        private HttpClientInterface $httpClient,
        private PusherSignature $pusherSignature,
        private PusherConfig $pusherConfig,
    ) {}

    /**
     * @throws BroadcastException|PusherException
     */
    public function broadcast(
        string|Channel $channel,
        string $event,
        array $data,
        ?string $id = null,
    ): void {
        $channel = Channel::from($channel);

        if ($event === '') {
            throw BroadcastException::emptyEventName();
        }

        $channelName = $this->channelName($channel);

        $body = $this->encodeJson([
            'name' => $event,
            'channels' => [$channelName],
            'data' => $this->encodeJson($data, $event),
        ], $event);

        $this->sendRequest($channel, $body);
    }

    /**
     * @throws BroadcastException|PusherException
     */
    public function dispatch(
        BroadcastableInterface $broadcastable,
    ): void {
        foreach ($broadcastable->channels() as $channel) {
            $this->broadcast($channel, $broadcastable->event(), $broadcastable->payload());
        }
    }

    /**
     * @throws BroadcastException|PusherException
     */
    private function sendRequest(
        Channel $channel,
        string $body,
    ): void {
        $path = "/apps/{$this->pusherConfig->appId}/events";
        $query = $this->pusherSignature->signedQuery('POST', $path, $body, time());

        try {
            $response = $this->httpClient->post(
                $this->pusherConfig->baseUrl() . $path . '?' . http_build_query($query),
                [
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => $body,
                    'timeout' => $this->pusherConfig->timeout,
                ],
            );
        } catch (HttpException $e) {
            throw BroadcastException::publishFailed(self::DRIVER, $channel->name, $e->getMessage(), $e);
        }

        if (!$response->isSuccessful()) {
            throw BroadcastException::publishFailed(
                self::DRIVER,
                $channel->name,
                "server responded with HTTP {$response->statusCode()}: {$response->body()}",
            );
        }
    }

    /**
     * @throws BroadcastException
     */
    private function channelName(Channel $channel): string
    {
        $channelName = $channel->isPrivate() ? 'private-' . $channel->name : $channel->name;

        if (preg_match(self::CHANNEL_NAME_PATTERN, $channelName) !== 1) {
            throw BroadcastException::invalidChannelName(
                self::DRIVER,
                $channel->name,
                'letters, digits and _ - = @ , . ; (164 characters at most, including the private- prefix)',
            );
        }

        return $channelName;
    }

    /**
     * @param array<string, mixed> $data
     * @throws BroadcastException
     */
    private function encodeJson(
        array $data,
        string $event,
    ): string {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw BroadcastException::unencodablePayload($event, $e->getMessage(), $e);
        }
    }
}
