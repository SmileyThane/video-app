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
    protected $signature = 'conversion:start {path} {bucket?} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for starting video conversion';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        ProcessConversion::dispatch($this->argument('bucket'), $this->argument('path'));
        return 0;
    }
}
