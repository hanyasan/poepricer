<?php

/**
 * Router script for PHP's built-in server, based on Laravel's own server.php.
 * Used instead of `artisan serve` because that command strips environment
 * variables (DB_*, REDIS_*) when it spawns the server process.
 *
 * The document root is read from $_SERVER['DOCUMENT_ROOT'] rather than
 * getcwd(), because `php -S ... -t public` does not change the working
 * directory to the document root.
 */

$publicPath = rtrim($_SERVER['DOCUMENT_ROOT'] ?? getcwd(), '/');

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
