# marko/broadcasting-pusher

Pusher-protocol broadcasting driver --- realtime events through hosted Pusher, Soketi, or Laravel Reverb.

## Installation

```bash
composer require marko/broadcasting-pusher
```

This installs `marko/broadcasting`. You also need an HTTP client driver such as `marko/http-guzzle`.

## Quick Example

```php
use Marko\Broadcasting\PresenceChannel;
use Marko\Broadcasting\PrivateChannel;

// BroadcasterInterface is bound to PusherBroadcaster
$broadcaster->broadcast('shows.42', 'seat.sold', ['seat' => 'A1']);
$broadcaster->broadcast(new PrivateChannel('orders.7'), 'order.shipped', ['id' => 7]); // sent as private-orders.7
$broadcaster->broadcast(new PresenceChannel('rooms.3'), 'message.posted', ['text' => 'Hi']); // sent as presence-rooms.3
```

Private and presence channels are authorized at `POST /broadcasting/auth` (pusher-js / Laravel Echo `authEndpoint`).

## Documentation

Full usage, API reference, and examples: [marko/broadcasting-pusher](https://marko.build/docs/packages/broadcasting-pusher/)
