<?php

namespace App\Services\Billing;

use Carbon\Carbon;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class BillingLogger
{
    private $now = null;

    private $log = null;

    private $file = '';

    private $name = 'billing';

    public function __construct($subPath = null)
    {
        $this->now = Carbon::now();

        $this->file = is_null($subPath) ? $this->now->format('Y-m-d').'.log' : $subPath.'/'.$this->now->format('Y-m-d').'.log';

        $this->log = new Logger($this->name);

        try {
            $this->log->pushHandler(new StreamHandler($this->path(), Logger::DEBUG));
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
    }

    public function info(...$args)
    {
        return $this->log->info(...$args);
    }

    public function error(...$args)
    {
        return $this->log->error(...$args);
    }

    public function start() : void
    {
        $this->log->info('------------------------------------------------------------');
    }

    public function finish() : void
    {
        $this->log->info('------------------------------------------------------------');
    }

    public function test() : void
    {
        $this->log->info('--------------------------- test ---------------------------');
    }

    public function production()
    {
        $this->log->info('------------------------ production ------------------------');
    }

    public function runTest()
    {
        $this->file = $this->now->format('Y-m-d').'_test.log';
        try {
            $this->log->pushHandler(new StreamHandler($this->path(), Logger::DEBUG));
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
    }

    public function runProduction()
    {
        /*
        $this->file = $this->now->format('Y-m-d').'.log';
        try {
            $this->log->pushHandler(new StreamHandler($this->path(), Logger::DEBUG));
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
        */
    }

    private function path()
    {
        return storage_path('logs/billing/'.$this->file);
    }
}