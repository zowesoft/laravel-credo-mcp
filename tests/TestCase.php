<?php

declare(strict_types=1);

namespace ZoweSoft\LaravelCredoMcp\Tests;

use Orchestra\Testbench\TestCase as Testbench;
use ZoweSoft\LaravelCredo\CredoServiceProvider;
use ZoweSoft\LaravelCredoMcp\CredoMcpServiceProvider;

abstract class TestCase extends Testbench
{
    public const TEST_PUBLIC_KEY = '0PUB-TEST-KEY';

    public const TEST_SECRET_KEY = '0PRI-TEST-KEY';

    protected function getPackageProviders($app): array
    {
        return [
            CredoServiceProvider::class,
            CredoMcpServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('credo.mode', 'DEMO');
        $app['config']->set('credo.public_key', self::TEST_PUBLIC_KEY);
        $app['config']->set('credo.secret_key', self::TEST_SECRET_KEY);
        $app['config']->set('credo.timeout', 30);
        $app['config']->set('credo.retry_base_delay_ms', 0);
    }
}
