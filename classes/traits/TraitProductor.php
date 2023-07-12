<?php

namespace Waka\Productor\Classes\Traits;

use Closure;

trait TraitProductor
{
    /**
     * Sauvegarde le PDF généré à partir d'un template HTML vers un chemin spécifié.
     *
     * @param string $template
     * @param string $path
     * @param array $vars
     * @param Closure $callback
     * @return string
     */
    public static function send($template, $vars, $options, Closure $callback)
    {
        if (!self::isMethodAllowed('send')) {
            return null;
        }
        // Créer l'instance de pdf
        $creator = self::instanciateCreator($template, $vars, $options);
        // Appeler le callback pour définir les options
        $callback($creator);

        try {
            return $creator->send();
        } catch (\Exception $ex) {
            throw new \ApplicationException($ex);
        }
    }

    public static function show($template, $vars, $options, Closure $callback)
    {
        if (!self::isMethodAllowed('show')) {
            return null;
        }
        // Créer l'instance de pdf
        $creator = self::instanciateCreator($template, $vars, $options);
        // Appeler le callback pour définir les options
        $callback($creator);
        // Sauver le fichier pdf. 
        try {
            return $creator->show();
        } catch (\Exception $ex) {
            throw new \ApplicationException($ex);
        }
    }

    public static function download($template, $vars, $options,  Closure $callback)
    {
        if (!self::isMethodAllowed('download')) {
            return null;
        }
        // Créer l'instance de pdf
        $creator = self::instanciateCreator($template, $vars, $options);
        // Appeler le callback pour définir les options
        $callback($creator);
        // Sauver le fichier pdf. 
        try {
            return $creator->download();
        } catch (\Exception $ex) {
            throw new \ApplicationException($ex);
        }
    }

    public static function saveTo($template, $vars, $options, $path, Closure $callback)
    {
        if (!self::isMethodAllowed('saveTo')) {
            return null;
        }
        // Créer l'instance de pdf
        $creator = self::instanciateCreator($template, $vars, $options);
        // Appeler le callback pour définir les options
        $callback($creator);
        // Sauver le fichier pdf. 
        try {
            return $creator->saveTo($path);
        } catch (\Exception $ex) {
            throw new \ApplicationException($ex);
        }
    }

    /**
     * Instancie un objet Browsershot avec une URL.
     *
     * @param string $url
     * @return \Spatie\Browsershot\Browsershot
     */
    private static function instanciateCreator($template, $vars, $options)
    {
        $prductorClass = self::getConfig()['productorCreator'];
        $mail = new $prductorClass($template, $vars, $options);
        return $mail;
    }

    public static function getProductors($driverConfig, $modelClass, $user)
    {
        trace_log($driverConfig);
        $permissions = $driverConfig['permissions'] ?? null;
        $exclude = $driverConfig['exclude'] ?? null;
        $productorModel = self::getConfig()['productorModel'];
        $productors;
        //Filtre sur les permissions
        trace_log('temp');
        if (strpos($permissions, '*') !== false) {
            $permissions = str_replace('*', '%', $permissions);
            $productors = $productorModel::where('slug', 'like', $permissions);
        } else {
            $productors = $productorModel::where('slug', $permissions);
        }

        return $productors->get(['name', 'slug', 'id'])->keyby('id')->toArray();

    }

    private static function isMethodAllowed($methodName)
    {
        $configMethods =  self::config()['methods'];
        if (in_array($methodName, $configMethods)) {
            return true;
        } else {
            return false;
        }
    }
}
