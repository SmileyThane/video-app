<?php


namespace App\Providers;

use App\Http\Controllers\StreamRemoteDownload;
use GuzzleHttp\Client;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\ServiceProvider;

class StreamRemoteServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
        ResponseFactory::macro('streamRemoteDownload', function (...$parameters) {
            return (new StreamRemoteDownload($this, new Client))(...$parameters);
        });
    }
}
