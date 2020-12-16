<?php

namespace App\Jobs;

use App\Http\Controllers\VideoController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessConversion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bucket;
    protected $path;

    /**
     * Create a new job instance.
     *
     * @param $bucket
     * @param $path
     */
    public function __construct($path, $bucket = null)
    {
        $this->bucket = $bucket;
        $this->path = $path;
    }

    /**
     * Execute the job.
     *
     * @return string
     */
    public function handle(): string
    {
        (new VideoController())->run($this->path, $this->bucket);
        return $this->job->getJobId();
    }
}
