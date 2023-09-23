<?php

namespace Waka\Productor\Classes\Traits;

use System\Classes\PluginManager;
use Closure;
use Waka\Wutils\Classes\PermissionsChecker;

trait TraitProductor
{

    /**
     * Instancieation de la class creator
     *
     * @param string $url
     * @return \Spatie\Browsershot\Browsershot
     */
    private static function instanciateCreator(string $templateCode, array $vars, array $options)
    {
        $productorClass = self::getConfig()['productorCreator'];
        $class = new $productorClass($templateCode, $vars, $options);
        return $class;
    }

    public static function getProductor($slug)
    {
        $productorClass = self::getConfig()['productorModel'];
        if (method_exists($productorClass, 'findBySlug')) {
            //trace_log('find by clug existe************');
            return $productorClass::findBySlug($slug);
        } else {
            //trace_log('find by clug existe PAS ************');
            //trace_log($productorClass);
            return $productorClass::where('slug', $slug)->first();
        }
    }

    public static function getProductors($driverConfig, $globalPermissions = [])
    {
        //trace_log('driverConfig!', $driverConfig);
        //trace_log('globalPermissions', $globalPermissions);
        $pc = new PermissionsChecker();
        $globalPermissions = $globalPermissions;
        $pc->mergeRules($globalPermissions);
        $driverPermissions = $driverConfig['permissions'] ?? [];
        $pc->mergeRules($driverPermissions);
        // Recherche dans les fichiers enregistrés.
        $staticTemplatesList = [];
        if ($registrerFnc = self::getConfig()['productorFilesRegistration'] ?? false) {
            $templatesData = PluginManager::instance()->getRegistrationMethodValues($registrerFnc);
            $templatesData = self::flattenPluginBundle($templatesData);
            $pc->mergeKeyedRules($templatesData);
        }
        $noProductodBdd = self::getConfig()['noproductorBdd'] ?? false;
        if (!$noProductodBdd) {
            $productorModel = self::getConfig()['productorModel'];
            // Filtre sur les permissions
            $bddTemplatesList = $productorModel::get(['name', 'slug'])->keyBy('slug')->toArray();
            $pc->mergeKeyedRules($bddTemplatesList);
        }
        $autorisedTemplates = $pc->checkKeyedCodes();
        return $autorisedTemplates;
    }

    private static function flattenPluginBundle($array)
    {
        $newArray = [];

        foreach ($array as $subArray) {
            foreach ($subArray as $key => $value) {
                $newArray[$key] = $value;
            }
        }
        return $newArray;
    }

    // private static function isMethodAllowed($methodName)
    // {
    //     $configMethods =  self::getConfig()['methods'];
    //     if (in_array($methodName, $configMethods)) {
    //         return true;
    //     } else {
    //         return false;
    //     }
    // }

    private static function getAndSetAsks($productorModel, $formWidget)
    {
        if (method_exists(get_class($productorModel), 'getProductorAsks')) {
            //trace_log($productorModel->name);
            $fields = $productorModel->getProductorAsks();
            //trace_log($fields);
            $askFields = [
                'askfield' => [
                    'label' => 'Champs modificables',
                    'usePanelStyles' => false,
                    'type' => 'nestedform',
                    'form' => [
                        'fields' => $fields,
                    ],

                ]
            ];
            $formWidget->addFields($askFields);
            $values = array_map(function ($item) {
                return $item['default'];
            }, $fields);
            $formWidget->getField('askfield')->value =  $values;
            return $formWidget;
        } else {
            return $formWidget;
        }
    }

    // /**
    //  * Envoyer un email
    //  *
    //  * @param string $template
    //  * @param string $path
    //  * @param array $vars
    //  * @param Closure $callback
    //  * @return string
    //  */
    // public static function sendToApi($template, $vars, $options, Closure $callback)
    // {
    //     if (!self::isMethodAllowed('sendToApi')) {
    //         return null;
    //     }
    //     // Créer l'instance de pdf
    //     // $creator = self::instanciateCreator($template, $vars, $options);
    //     // // Appeler le callback pour définir les options
    //     // $callback($creator);

    //     // try {
    //     //     return $creator->sendEmail();
    //     // } catch (\Exception $ex) {
    //     //     throw new \ApplicationException($ex);
    //     // }
    // }

    // public static function show($template, $vars, $options, Closure $callback)
    // {
    //     if (!self::isMethodAllowed('show')) {
    //         return null;
    //     }
    //     // Créer l'instance de pdf
    //     $creator = self::instanciateCreator($template, $vars, $options);
    //     // Appeler le callback pour définir les options
    //     $callback($creator);
    //     // Sauver le fichier pdf. 
    //     try {
    //         return $creator->show();
    //     } catch (\Exception $ex) {
    //         throw new \ApplicationException($ex);
    //     }
    // }

    // public static function importData($template, $vars, $options,  Closure $callback)
    // {
    //     if (!self::isMethodAllowed('importData')) {
    //         return null;
    //     }
    //     // Créer l'instance
    //     $creator = self::instanciateCreator($template, $vars, $options);
    //     // Appeler le callback pour définir les options
    //     $callback($creator);
    //     // Sauver le fichier. 
    //     try {
    //         return $creator->importData();
    //     } catch (\Exception $ex) {
    //         throw new \ApplicationException($ex);
    //     }
    // }

    // public static function download($template, $vars, $options,  Closure $callback)
    // {
    //     if (!self::isMethodAllowed('download')) {
    //         return null;
    //     }
    //     // Créer l'instance
    //     $creator = self::instanciateCreator($template, $vars, $options);
    //     // Appeler le callback pour définir les options
    //     $callback($creator);
    //     // Sauver le fichier. 
    //     try {
    //         return $creator->download();
    //     } catch (\Exception $ex) {
    //         throw new \ApplicationException($ex);
    //     }
    // }
}
