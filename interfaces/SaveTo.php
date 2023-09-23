<?php 

namespace Waka\Productor\Interfaces;
use Closure;

interface SaveTo
{
    public static function saveTo(string $slug, array $vars, array $options, string $path,  Closure $callback);
}




