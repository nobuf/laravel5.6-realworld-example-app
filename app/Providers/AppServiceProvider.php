<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Tymon\JWTAuth\Http\Parser\AuthHeaders;
use Tymon\JWTAuth\Http\Parser\Parser;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTokenParser();
    }

    private function registerTokenParser()
    {
        $this->app->singleton('tymon.jwt.parser', function ($app) {
            $parser = new Parser(
                $app['request'],
                [
                    // Use `Token` instead of `Bearer`
                    (new AuthHeaders())->setHeaderPrefix('token'),
                ]
            );

            $app->refresh('request', $parser, 'setRequest');

            return $parser;
        });
    }
}
