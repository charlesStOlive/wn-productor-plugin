<?php 

namespace Waka\Productor\Interfaces;
use Closure;

interface XXBaseProductor
{

    public static function getConfig();

    public static function updateFormwidget(string $slug, \Backend\Widgets\Form $formWidget);

    public static function execute(string $templateCode, string $productorHandler, array $allDatas):array ;

}