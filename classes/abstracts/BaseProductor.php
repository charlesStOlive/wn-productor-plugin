<?php

namespace Waka\Productor\Classes\Abstracts;

use System\Classes\PluginManager;

use Arr;
use Waka\Productor\Classes\CheckAcceptedModel;
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
        $this->dsMap = $dsMapKey ?: Arr::get($allDatas, 'config.dsMap', null);
        //trace_log($this->dsMap);
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
            //trace_log($this->data);
        } else {
            //Excel n a pas de tragetproductor par ex. 
            //throw new \ApplicationException('probleme targetModel');
        }
    }



    public static function getProductor($slug)
    {
        $productorClass = self::getStaticConfig('productorModel');
        // trace_log($productorClass);
        if (method_exists($productorClass, 'findBySlug')) {
            // trace_log('find by clug existe************');
            return $productorClass::findBySlug($slug);
        } else if(self::getStaticConfig('noProductorBdd')) {
            //Il ny a pas de modèle dans la bdd on retourne vide, notamement pour les dsAsks
            return null;
        } else {
            // trace_log('find by clug existe PAS ************');
            //trace_log($productorClass);
            return $productorClass::where('slug', $slug)->first();
        }
    }

    public static function getModels($modelKey, $modelConfig)
    {
        $modelAccepted = new CheckAcceptedModel($modelKey);
        
        $noProductorBdd = self::getStaticConfig('noProductorBdd') ?? false;
        //trace_log($noProductorBdd);
        if (!$noProductorBdd) {
            //trace_log('recherche----------------');
            $productorModel = self::getStaticConfig('productorModel');
            // Filtre sur les permissions
            $bddTemplatesList = $productorModel::get(['name', 'slug'])->keyBy('slug')->toArray();
            // trace_log('bddTemplatesList!!',$bddTemplatesList);
            $modelAccepted->addModels($bddTemplatesList);
        }
        if ($registrerFnc = self::getStaticConfig('productorFilesRegistration') ?? false) {
            $productorModel = self::getStaticConfig('productorModel');
            $templatesData = PluginManager::instance()->getRegistrationMethodValues($registrerFnc);
            $templatesData = self::flattenPluginBundle($templatesData);
            $templateToreturn = [];
            // trace_log('templatesData!!',$templatesData);
            foreach($templatesData as $templateKey=>$template) {
                trace_log($template);
                if(is_array($template)) {
                    //cas des excel ou autre qui ont la config des élements dans le register. 
                    $templateToreturn[$templateKey] = $template;
                } else {
                    try {
                        $model = $productorModel::findBySlug($template);
                        $templateToreturn[$model->slug] = [
                        'name' => $model->name,
                    ];
                    } catch (\Exception $ex) {
                        trace_log($ex->getMessage());
                        //Model non compatible exemple System\Models\MailTemplate
                    }
                    
                    
                }
            }
            trace_log('templateToreturn!!',$templateToreturn);
            $modelAccepted->addModels($templateToreturn);
        }
        $autorisedTemplates = $modelAccepted->check();
        foreach($autorisedTemplates as $key=>$template) {
            if($template['class'] ?? false) {
                $template['class'] = addslashes($template['class']);
            }
            $autorisedTemplates[$key] = array_merge($template, $modelConfig);
        }
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
        if(!$productorModel) {
            return $formWidget;
        }
        //
        $fields = null;
        if(is_array($productorModel)) {
            //cas des excel qui ont une config en excel
            $fields = $productorModel['prod_asks'] ?? null;
        } else {
            $fields = $productorModel->prod_asks ?? null ;
        }
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
