<?php

namespace Waka\Productor\Classes\Traits;

use System\Classes\PluginManager;
use Closure;
use Waka\Wutils\Classes\PermissionsChecker;

trait TraitProductor
{
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
}
