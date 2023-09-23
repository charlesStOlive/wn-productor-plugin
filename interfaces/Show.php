<?php 

namespace Waka\Productor\Interfaces;
use Closure;

interface Show
{
    public static function show(string $slug, array $vars, array $options,  Closure $callback);
}




