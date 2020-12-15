<?php


namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Routing\ResponseFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamRemoteDownload
{
    /**
     * Response factory.
     *
     * @var ResponseFactory
     */
    protected $factory;

    /**
     * Http client.
     *
     * @var Client
     */
    protected $client;

    /**
     * Instantiate a new handler isntance.
     *
     * @param ResponseFactory $factory
     * @param Client $client
     */
    public function __construct(ResponseFactory $factory, Client $client)
    {
        $this->factory = $factory;
        $this->client = $client;
    }

    /**
     * Create a new streamed response.
     *
     * @param  string $url
     * @param  string|null $name
     * @param  array $headers
     * @param  string $disposition
     * @param  int $chunk
     * @return StreamedResponse
     */
    public function __invoke(string $url, string $name = null, array $headers = [], $disposition = 'attachment', int $chunk = 90480): StreamedResponse
    {
        return $this->factory->streamDownload(function () use ($url, $chunk) {
            $this->stream($url, $chunk);
        }, $name, $headers, $disposition);
    }

    /**
     * Stream contents of a given url.
     *
     * @param string $url
     * @param int $chunk
     * @return void
     * @throws GuzzleException
     */
    protected function stream(string $url, int $chunk): void
    {
        $body = $this->client->get($url, ['stream' => true])->getBody();

        while (! $body->eof()) {
            echo $body->read($chunk);
        }
    }
}
