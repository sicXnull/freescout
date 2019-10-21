<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'freescout:clear-cache {--doNotCacheConfig} {--doNotGenerateVars}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear application cache and cache config';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->call('clear-compiled');
        $this->call('cache:clear');
        $this->call('view:clear');
        if ($this->option('doNotCacheConfig')) {
            $this->call('config:clear');
        } else {
            $this->call('config:cache');
        }
        // Regenerate vars to get new data from .env
        if (!$this->option('doNotGenerateVars')) {
            $this->call('freescout:generate-vars');
        }
    }
}
