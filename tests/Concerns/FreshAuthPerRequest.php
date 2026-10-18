<?php

namespace Tests\Concerns;

/**
 * Within one test the app (and Sanctum's guard) is reused between requests, so the first
 * authenticated user would stay logged in for every later request. A real client sends a new
 * request each time, so forget the cached user before every call.
 */
trait FreshAuthPerRequest
{
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
