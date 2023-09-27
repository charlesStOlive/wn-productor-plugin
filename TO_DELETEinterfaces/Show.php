<?php 

namespace Waka\Productor\Interfaces;
use Closure;

interface XXShow
{
    public static function show(string $slug, array $vars, array $options,  Closure $callback);
}




