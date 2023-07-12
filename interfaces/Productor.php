<?php 

namespace Waka\Productor\Interfaces;
use Closure;

interface Productor
{

    public static function getConfig();

    public static function send(string $template, array $vars, array $options, Closure $callback);

    public static function show(string $template, array $vars, array $options, Closure $callback);

    public static function download(string $template, array $vars, array $options,  Closure $callback);

    public static function saveTo(string $template, array $vars, array $options, string $path, Closure $callback);
}