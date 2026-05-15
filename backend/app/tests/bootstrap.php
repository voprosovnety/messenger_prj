<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    // phpunit.dist.xml sets $_SERVER['APP_ENV']=test, but the container has
    // APP_ENV=dev as a real env variable, which pollutes $_ENV['APP_ENV'].
    // KernelTestCase::createKernel() reads $_ENV first, so we must align all
    // three sources before bootEnv() runs.
    if (isset($_SERVER['APP_ENV'])) {
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'];
        putenv('APP_ENV='.$_SERVER['APP_ENV']);
    }
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
