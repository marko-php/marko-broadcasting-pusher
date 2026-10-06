<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Pusher\Exceptions;

use Marko\Broadcasting\Exceptions\BroadcastException;

class PusherException extends BroadcastException
{
    public static function missingCredentials(): self
    {
        return new self(
            message: 'Pusher credentials are not configured.',
            context: "'broadcasting-pusher.app_id', 'broadcasting-pusher.key' and 'broadcasting-pusher.secret' must all be set",
            suggestion: 'Set PUSHER_APP_ID, PUSHER_APP_KEY and PUSHER_APP_SECRET in your environment.',
        );
    }
}
