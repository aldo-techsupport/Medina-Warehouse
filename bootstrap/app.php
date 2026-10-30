<?php

use App\Http\Middleware\CheckMenuAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Polyfill for environments where PHP fileinfo extension is missing or failed to initialize (e.g. PHP 8.5 dev builds)
if (! defined('FILEINFO_NONE')) {
    define('FILEINFO_NONE', 0);
}
if (! defined('FILEINFO_MIME_TYPE')) {
    define('FILEINFO_MIME_TYPE', 16);
}
if (! class_exists('finfo')) {
    class finfo
    {
        public function __construct(int $flags = FILEINFO_NONE, ?string $magic_database = null) {}

        public function file(string $filename, int $flags = FILEINFO_NONE, $context = null): string|false
        {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            return match ($ext) {
                'mp4', 'm4v' => 'video/mp4',
                'webm' => 'video/webm',
                'mov' => 'video/quicktime',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                'json' => 'application/json',
                'txt' => 'text/plain',
                'csv' => 'text/csv',
                default => 'application/octet-stream',
            };
        }

        public function buffer(string $string, int $flags = FILEINFO_NONE, $context = null): string|false
        {
            return 'application/octet-stream';
        }
    }
}

if (! function_exists('finfo_open')) {
    function finfo_open(int $flags = FILEINFO_NONE, ?string $magic_database = null): finfo|false
    {
        return new finfo($flags, $magic_database);
    }
}

if (! function_exists('finfo_file')) {
    function finfo_file(finfo $finfo, string $filename, int $flags = FILEINFO_NONE, $context = null): string|false
    {
        return $finfo->file($filename, $flags, $context);
    }
}

if (! function_exists('finfo_buffer')) {
    function finfo_buffer(finfo $finfo, string $string, int $flags = FILEINFO_NONE, $context = null): string|false
    {
        return $finfo->buffer($string, $flags, $context);
    }
}

if (! function_exists('finfo_close')) {
    function finfo_close(finfo $finfo): bool
    {
        return true;
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'shopee/webhook',
            'api/shopee/webhook',
            'api/shopee-bridge/webhook',
        ]);

        $middleware->redirectTo(
            guests: '/login',
            users: fn (Request $request) => $request->user()?->getRedirectUrl() ?? route('dashboard'),
        );

        $middleware->alias([
            'menu' => CheckMenuAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
