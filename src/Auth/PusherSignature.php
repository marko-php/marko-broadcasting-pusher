<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Pusher\Auth;

use Marko\Broadcasting\Pusher\Exceptions\PusherException;
use Marko\Broadcasting\Pusher\PusherConfig;

/**
 * HMAC-SHA256 signing for the Pusher HTTP API and private-channel authorization,
 * implemented per the published Pusher protocol (also spoken by Soketi and Laravel Reverb).
 */
readonly class PusherSignature
{
    private const string AUTH_VERSION = '1.0';

    public function __construct(
        private PusherConfig $pusherConfig,
    ) {}

    /**
     * Sign "METHOD\nPATH\nsorted-query" with the app secret.
     *
     * @param array<string, string> $query
     * @throws PusherException
     */
    public function sign(
        string $method,
        string $path,
        array $query,
    ): string {
        $query = array_change_key_case($query);
        ksort($query);

        $pairs = [];
        foreach ($query as $name => $value) {
            $pairs[] = "$name=$value";
        }

        return hash_hmac('sha256', "$method\n$path\n" . implode('&', $pairs), $this->secret());
    }

    /**
     * The authentication query parameters for an HTTP API request, including auth_signature.
     *
     * @return array<string, string>
     * @throws PusherException
     */
    public function signedQuery(
        string $method,
        string $path,
        string $body,
        int $timestamp,
    ): array {
        $query = [
            'auth_key' => $this->pusherConfig->key,
            'auth_timestamp' => (string) $timestamp,
            'auth_version' => self::AUTH_VERSION,
            'body_md5' => md5($body),
        ];

        $query['auth_signature'] = $this->sign($method, $path, $query);

        return $query;
    }

    /**
     * The `auth` value a client presents to subscribe to a private channel: "key:HMAC(secret, socket_id:channel)".
     *
     * @throws PusherException
     */
    public function channelAuth(
        string $socketId,
        string $channelName,
    ): string {
        return $this->pusherConfig->key . ':' . hash_hmac('sha256', "$socketId:$channelName", $this->secret());
    }

    /**
     * The `auth` value for a presence channel: "key:HMAC(secret, socket_id:channel:channel_data)".
     * `$channelData` is the already-encoded JSON string the client will receive, signed verbatim.
     *
     * @throws PusherException
     */
    public function presenceChannelAuth(
        string $socketId,
        string $channelName,
        string $channelData,
    ): string {
        return $this->pusherConfig->key . ':' . hash_hmac(
            'sha256',
            "$socketId:$channelName:$channelData",
            $this->secret(),
        );
    }

    /**
     * @throws PusherException
     */
    private function secret(): string
    {
        if (
            $this->pusherConfig->key === ''
            || $this->pusherConfig->secret === ''
            || $this->pusherConfig->appId === ''
        ) {
            throw PusherException::missingCredentials();
        }

        return $this->pusherConfig->secret;
    }
}
