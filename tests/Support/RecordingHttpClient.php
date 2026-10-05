<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Pusher\Tests\Support;

use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\Exceptions\ConnectionException;
use Marko\Http\HttpResponse;
use Override;

/**
 * Local HttpClientInterface double that records requests and returns a canned response.
 */
class RecordingHttpClient implements HttpClientInterface
{
    /**
     * @var list<array{method: string, url: string, options: array<string, mixed>}>
     */
    public array $requests = [];

    public function __construct(
        private readonly HttpResponse $response = new HttpResponse(200, 'urn:uuid:1'),
        private readonly ?ConnectionException $exception = null,
    ) {}

    /**
     * @throws ConnectionException
     */
    #[Override]
    public function request(
        string $method,
        string $url,
        array $options = [],
    ): HttpResponse {
        $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->response;
    }

    /**
     * @throws ConnectionException
     */
    #[Override]
    public function get(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('GET', $url, $options);
    }

    /**
     * @throws ConnectionException
     */
    #[Override]
    public function post(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('POST', $url, $options);
    }

    /**
     * @throws ConnectionException
     */
    #[Override]
    public function put(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('PUT', $url, $options);
    }

    /**
     * @throws ConnectionException
     */
    #[Override]
    public function patch(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('PATCH', $url, $options);
    }

    /**
     * @throws ConnectionException
     */
    #[Override]
    public function delete(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('DELETE', $url, $options);
    }
}
