<?php

namespace Waka\Productor\Classes\Abstracts;

use System\Classes\PluginManager;

use Arr;
use Waka\Wutils\Classes\PermissionsChecker;

abstract class BaseProductor
{
    protected static $config;
    protected $modelId;
    protected $modelClass;
    protected $dsMap;
    protected $prodAsks;
    protected $dsParams;
    protected $data = [];
    protected $targetModel;

    

    public static function getStaticConfig($configKey = null) {
        $config = static::$config;
        $config['label'] = \Lang::get($config['label']);
        $config['description'] = \Lang::get($config['description']);
        if(!$configKey) {
            return $config;
        } else {
            return $config[$configKey] ?? null;
        }
        
    }

    protected function getBaseVars($allDatas , $dsMapKey = null) {
        $this->modelId = Arr::get($allDatas, 'modelId');
        $this->modelClass = Arr::get($allDatas, 'modelClass');
        $this->dsMap = $dsMapKey ?: Arr::get($allDatas, 'dsMap', null);
        $this->prodAsks = Arr::get($allDatas, 'prod_asks', []);
        $this->dsParams = Arr::get($allDatas, 'productorDataArray.ds_map_config', []);
        if($this->dsParams) {
            foreach ($this->dsParams as $key => $value) {
                $this->dsParams[$key] = ['params' => $value];
            }
        }
        //On instancie les$data avec prod_ask si existe sinon le tableau est vide
        $this->data =  ['prod_asks' => $this->prodAsks];
        //
        $this->targetModel = $this->modelClass::find($this->modelId);
        if ($this->targetModel) {
            $this->data = array_merge($this->data, $this->targetModel->dsMap($this->dsMap, $this->dsParams));
        }
    }



    public static function getProductor($slug)
    {
        $productorClass = self::getStaticConfig('productorModel');
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
        if ($registrerFnc = self::getStaticConfig('productorFilesRegistration') ?? false) {
            $templatesData = PluginManager::instance()->getRegistrationMethodValues($registrerFnc);
            $templatesData = self::flattenPluginBundle($templatesData);
            $pc->mergeKeyedRules($templatesData);
        }
        $noProductodBdd = self::getStaticConfig('noproductorBdd') ?? false;
        if (!$noProductodBdd) {
            $productorModel = self::getStaticConfig('productorModel');
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

    public static function getDsAsks($formWidget, $slug)
    {
        $productorModel = self::getProductor($slug);
        $fields = $productorModel->prod_asks;
        //trace_log($fields);
        if (!$fields) {
            return $formWidget;
        }
        $formWidget->addFields([
            'prod_asks' => [
                'label' => 'Champs modificables',
                'usePanelStyles' => false,
                'type' => 'blocks',
                'style' => 'collapsed',
                'readOnly_bcode' => true,
                'noAddBlock' => true,
                'bcodeReadOnly' => true,
            ],
        ]);
        $dsAskWidget = $formWidget->getField('prod_asks');
        $dsAskWidget->value = $fields;
        return $formWidget;
    }
}
