<?php

namespace App\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use RdKafka\Conf;
use RdKafka\Producer;

class KafkaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Producer::class, function ($app) {
            $conf = new Conf();
            $conf->set('metadata.broker.list', env('KAFKA_BROKERS'));
            return new Producer($conf);
        });
    }

    public function boot()
    {
        //
    }
}
