<?php
/**
 * Created by PhpStorm.
 * User: lukin
 * Date: 04/02/2017
 * Time: 13:13
 */

namespace Lukin\Textractor;


use Illuminate\Support\ServiceProvider;

class TextractorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path('textractor.php')
        ]);

        $this->mergeConfigFrom(__DIR__ . '/config.php', 'textractor');

    }

    /**
     * Register services
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Textractor::class, function ($app) {
            return new Textractor($app['config']->get('textractor'));
        });


    }
}