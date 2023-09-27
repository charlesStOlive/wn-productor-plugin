<?php 

namespace Waka\Productor\Interfaces;
use Closure;

interface XXSaveTo
{
    public static function saveTo(string $slug, array $vars, array $options, string $path,  Closure $callback);
}




