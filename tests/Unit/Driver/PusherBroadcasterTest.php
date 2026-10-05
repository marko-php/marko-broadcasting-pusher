<?php

declare(strict_types=1);

use Marko\Broadcasting\BroadcastableInterface;
use Marko\Broadcasting\Exceptions\BroadcastException;
use Marko\Broadcasting\PrivateChannel;
use Marko\Broadcasting\Pusher\Auth\PusherSignature;
use Marko\Broadcasting\Pusher\Driver\PusherBroadcaster;
use Marko\Broadcasting\Pusher\PusherConfig;
use Marko\Broadcasting\Pusher\Tests\Support\RecordingHttpClient;
use Marko\Http\Exceptions\ConnectionException;
use Marko\Http\HttpResponse;

function pusherBroadcaster(RecordingHttpClient $httpClient): PusherBroadcaster
{
    $pusherConfig = new PusherConfig(
        appId: '3',
        key: '278d425bdf160c739803',
        secret: '7ad3773142a6692b25b8',
        host: 'soketi.test',
        port: 6001,
        scheme: 'http',
    );

    return new PusherBroadcaster(
        httpClient: $httpClient,
        pusherSignature: new PusherSignature($pusherConfig),
        pusherConfig: $pusherConfig,
    );
}

/**
 * @return array<string, mixed>
 */
function pusherRequestBody(RecordingHttpClient $httpClient, int $index = 0): array
{
    return json_decode($httpClient->requests[$index]['options']['body'], true);
}

/**
 * @return array<string, string>
 */
function pusherRequestQuery(RecordingHttpClient $httpClient): array
{
    parse_str((string) parse_url($httpClient->requests[0]['url'], PHP_URL_QUERY), $query);

    return $query;
}

describe('PusherBroadcaster', function (): void {
    it('posts name, channels and json data to the events endpoint', function (): void {
        $httpClient = new RecordingHttpClient(response: new HttpResponse(200, '{}'));

        pusherBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', ['seat' => 'A1']);

        $request = $httpClient->requests[0];

        expect($request['method'])->toBe('POST')
            ->and(strtok($request['url'], '?'))->toBe('http://soketi.test:6001/apps/3/events')
            ->and($request['options']['headers']['Content-Type'])->toBe('application/json')
            ->and($request['options']['timeout'])->toBe(5)
            ->and(pusherRequestBody($httpClient))->toBe([
                'name' => 'seat.sold',
                'channels' => ['shows.42'],
                'data' => '{"seat":"A1"}',
            ]);
    });

    it('signs the request with auth query parameters', function (): void {
        $httpClient = new RecordingHttpClient(response: new HttpResponse(200, '{}'));

        pusherBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);

        $query = pusherRequestQuery($httpClient);
        $body = $httpClient->requests[0]['options']['body'];
        $unsigned = array_diff_key($query, ['auth_signature' => true]);

        expect($query['auth_key'])->toBe('278d425bdf160c739803')
            ->and($query['auth_version'])->toBe('1.0')
            ->and($query['body_md5'])->toBe(md5($body))
            ->and((int) $query['auth_timestamp'])->toBeGreaterThan(time() - 5)
            ->and($query['auth_signature'])->toBe(hash_hmac(
                'sha256',
                "POST\n/apps/3/events\n" . urldecode(http_build_query($unsigned)),
                '7ad3773142a6692b25b8',
            ));
    });

    it('prefixes private channels with private-', function (): void {
        $httpClient = new RecordingHttpClient(response: new HttpResponse(200, '{}'));

        pusherBroadcaster($httpClient)->broadcast(new PrivateChannel('orders.7'), 'order.shipped', []);

        expect(pusherRequestBody($httpClient)['channels'])->toBe(['private-orders.7']);
    });

    it('rejects channel names with characters Pusher does not allow', function (): void {
        $httpClient = new RecordingHttpClient();

        expect(fn () => pusherBroadcaster($httpClient)->broadcast('shows/42', 'seat.sold', []))
            ->toThrow(BroadcastException::class, "Channel name 'shows/42' is not valid for Pusher")
            ->and($httpClient->requests)->toBeEmpty();
    });

    it('rejects channel names longer than 164 characters', function (): void {
        expect(fn () => pusherBroadcaster(new RecordingHttpClient())->broadcast(str_repeat('a', 165), 'e', []))
            ->toThrow(BroadcastException::class, 'is not valid for Pusher');
    });

    it('throws BroadcastException when the api request fails', function (): void {
        $httpClient = new RecordingHttpClient(exception: new ConnectionException('Connection refused'));

        expect(fn () => pusherBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []))
            ->toThrow(BroadcastException::class, "Failed to broadcast to channel 'shows.42' via Pusher");
    });

    it('throws BroadcastException when the api answers with an error status', function (): void {
        $httpClient = new RecordingHttpClient(response: new HttpResponse(413, 'Payload too large'));

        try {
            pusherBroadcaster($httpClient)->broadcast('shows.42', 'seat.sold', []);
            $this->fail('Expected BroadcastException');
        } catch (BroadcastException $exception) {
            expect($exception->getMessage())->toContain('via Pusher')
                ->and($exception->getContext())->toContain('HTTP 413: Payload too large');
        }
    });

    it('rejects an empty event name', function (): void {
        expect(fn () => pusherBroadcaster(new RecordingHttpClient())->broadcast('shows.42', '', []))
            ->toThrow(BroadcastException::class, 'event name must not be empty');
    });

    it('broadcasts once per channel when dispatching a broadcastable', function (): void {
        $httpClient = new RecordingHttpClient(response: new HttpResponse(200, '{}'));

        pusherBroadcaster($httpClient)->dispatch(new readonly class () implements BroadcastableInterface
        {
            public function channels(): array
            {
                return ['orders', new PrivateChannel('orders.7')];
            }

            public function event(): string
            {
                return 'order.shipped';
            }

            public function payload(): array
            {
                return ['id' => 7];
            }
        });

        expect($httpClient->requests)->toHaveCount(2)
            ->and(pusherRequestBody($httpClient)['channels'])->toBe(['orders'])
            ->and(pusherRequestBody($httpClient, 1)['channels'])->toBe(['private-orders.7']);
    });
});
