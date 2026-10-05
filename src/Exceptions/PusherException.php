<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Pusher\Exceptions;

use Marko\Broadcasting\Exceptions\BroadcastException;

class PusherException extends BroadcastException
{
    public static function presenceChannelsNotSupported(string $channelName): self
    {
        return new self(
            message: "Presence channels are not supported yet (requested '$channelName').",
            context: 'While authorizing a Pusher channel subscription at /broadcasting/auth',
            suggestion: "Use a private channel ('private-' prefix, PrivateChannel in PHP) instead. Presence channel support (channel_data) is a planned follow-up.",
        );
    }

    public static function missingCredentials(): self
    {
        return new self(
            message: 'Pusher credentials are not configured.',
            context: "'broadcasting-pusher.app_id', 'broadcasting-pusher.key' and 'broadcasting-pusher.secret' must all be set",
            suggestion: 'Set PUSHER_APP_ID, PUSHER_APP_KEY and PUSHER_APP_SECRET in your environment.',
        );
    }
}
