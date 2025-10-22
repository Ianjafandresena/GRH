<?php
namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;

class Filters extends BaseFilters
{
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        'cors'          => \App\Filters\CorsFilter::class,
        'jwtauth'       => \App\Filters\JWTAuthFilter::class,
    ];

    public array $required = [
        'before' => [
            // 'forcehttps', // DÉSACTIVÉ
        ],
        'after' => [
            'toolbar',
        ],
    ];

    public array $globals = [
        'before' => [
            'cors'
        ],
        'after' => [
            'toolbar',
        ],
    ];


    public array $filters = [];
}