<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckConversionProcess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conversion:check {id}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check conversion process';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        if (DB::table('jobs')->find($this->argument('id'))) {
            Log::info('Process ' . $this->argument('id') . ' pending');
            echo "Process pending";
        } elseif (DB::table('failed_jobs')->find($this->argument('id'))) {
            Log::warning('Process ' . $this->argument('id') . ' closed with error!');
            echo "Process closed with error. Retrying...";
        } else {
            Log::info('Process ' . $this->argument('id') . ' finished');
            echo "Process finished";
        }
        echo "\n";
        return 0;
    }
}
