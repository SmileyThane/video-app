<?php

namespace App\Console\Commands;

use App\Jobs\ProcessConversion;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\PendingDispatch;

class StartConversionProcess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conversion:start {bucket, path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for starting video conversion';

    /**
     * Execute the console command.
     *
     * @return PendingDispatch
     */
    public function handle(): PendingDispatch
    {
        return ProcessConversion::dispatch($this->argument('bucket'), $this->argument('path'));

    }
}
