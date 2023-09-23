<?php 

namespace Waka\Productor\Interfaces;
use Closure;

interface Email
{

    /**
     * Envoyer un email
     *
     * @param string $template
     * @param array $vars
     * @param array $options
     * @param Closure $callback
     * @return string
     */
    public static function sendEmail(string $template, array $vars, array $options, Closure $callback);
}