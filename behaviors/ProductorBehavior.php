<?php

namespace Waka\Productor\Behaviors;

use Backend\Classes\ControllerBehavior;
use App;
use Str;
use Arr;
use Event;
use ValidationException;
use ApplicationException;

class ProductorBehavior extends ControllerBehavior
{
    /**
     * @var array Configuration values that must exist when applying the primary config file.
     * - modelClass: Class name for the model
     * - form: Form field definitions
     */
    protected $requiredConfig = ['modelClass', 'backendUrl', 'productor'];

    /**
     * @var mixed Configuration for this behaviour
     */
    public $productorConfig = 'config_waka.yaml';

    /**
     * @var Backend\Classes\WidgetBase Reference to the widget used for ....
     */
    protected $productorWidget;

    /**
     * @var Backend\Classes\WidgetBase Reference to the widget used for ....
     */
    protected $excelWidget;


    /**
     * @var array liste des erreurs remontés par les méthodes ou les evenements.  
     */
    public $errors;

    /**
     * $user = BackendAuth::getUser();
     */
    protected $user;


    /**
     * @var object config.  
     */
    protected $config;


    /**
     * 
     */
    private $driverManager;


    public function __construct($controller)
    {

        parent::__construct($controller);
        //
        $this->addCss('$/waka/productor/assets/css/productor.css', 'Waka.Productor');
        //
        $this->config = $this->makeConfig($controller->productorConfig ?: $this->productorConfig, $this->requiredConfig);
        $this->config->modelClass = Str::normalizeClassName($this->config->modelClass);
        //
        $this->user = \BackendAuth::getUser();
        //
        $this->errors = [];
        Event::listen('waka.productor::conditions.error', function ($error) {
            array_push($this->errors, $error);
        });
        Event::listen('waka_controller.action_bar.before_partial', function ($params) {
            if ($params->context == 'update' && $this->hasData()) return $this->makePartial('controller_btn');
        });
        $this->driverManager = App::make('waka.productor.drivermanager');
        //
        $this->excelWidget = $this->createFileImportWidget();
    }

    public function hasData()
    {
        $models = $this->config->productor['models'] ?? false;
        $handlers = $this->config->productor['handlers'] ?? false;
        return $models || $handlers;
    }

    public function onLauchProductor()
    {
        $targetModel = $this->config->modelClass::find(post('modelId'));
        $configFromWorkflow = Event::fire('controller.productor.update_productor', [$targetModel]);
        if ($configFromWorkflow = $configFromWorkflow[0] ?? false) {
            if (array_key_exists('productor', $configFromWorkflow)) {
                $workflowConfig = $configFromWorkflow['productor'];
                if(!$workflowConfig) $workflowConfig = [];
                //trace_log($workflowConfig);
                $this->config->productor = $workflowConfig;
            } 
        }
        
        // $configProductor = $this->controller->updateProductorConfig($this->config->productor);
        // if (!empty($configProductor)) {
        //     $this->config->productor = $configProductor;
        // }
        $drivers = $this->driverManager->getAuthorisedDrivers($this->config);



        $drivers = Arr::map($drivers, function ($value, $driverKey) {
            $label = Arr::get($value, 'config.label');
            $icon = Arr::get($value, 'config.icon');
            $data = Arr::get($value, 'productorModels');
            //trace_log($data);
            return [
                'label' => $label,
                'icon' => $icon,
                'productorModels' => $data
            ];
        });
        // trace_log($drivers);
        $manualHandlers = $this->getManualHandlers();
        //trace_log($manualHandlers);
        if (!empty($manualHandlers)) {
            $drivers['handlers'] = $manualHandlers;
        }
        //trace_log($drivers);
        $this->vars['modelId'] = post('modelId');
        $this->vars['drivers'] = $drivers;
        return $this->makePartial('popup');
    }

    protected function getManualHandlers()
    {
        $handlers = $this->config->productor['handlers'] ?? null;
        if (!$handlers) {
            return;
        }
        //trace_log($handlers);
        return [
            'label' =>  $handlers['label'] ?? 'Autre opération',
            'icon' => 'icon-cog',
            'productorModels' => $handlers['requests']
        ];
    }

    // public function updateProductorConfig($productorConfig)
    // {
    //     return $productorConfig;
    // }

    public function onSelectProductor()
    {
        //trace_log(post());
        //trace_sql();
        $modelId = post('modelId');
        $driverCode = post('driverCode');
        $productorSlug = post('productorSlug');
        //
        if ($driverCode == 'handlers') {
            //trace_log('c est un handler');
            //ProductorHandler est ici le code onMethod à utiliser.
            return $this->controller->{$productorSlug}();
        }
        $productorDriver = $this->driverManager->driver($driverCode);
        $additionalConfig = $this->getAdditionalConfig(post('addedConfig'));
        //trace_log('$additionalConfig 1 ',$additionalConfig);
        $dsMap = $additionalConfig['dsMap'] ?? false;
        //trace_log('onSelectProductor',$dsMap);
        //
        $targetModel = $this->config->modelClass::find($modelId);
        if ($productorDriver->getStaticConfig('use_import_file_widget') ?? false) {
            $this->productorWidget = $this->excelWidget;
        } else {
            $this->excelWidget = null;
            $this->productorWidget = $this->createProductorWidget($productorDriver->getStaticConfig('productor_yaml_config'), $targetModel);
            $this->productorWidget = $productorDriver::updateFormwidget($productorSlug, $this->productorWidget, $additionalConfig);
            $this->productorWidget = $productorDriver::getDsAsks($this->productorWidget, $productorSlug);
            $configDsFields = $targetModel->dsGetParamsConfig($dsMap);
            //trace_log('onSelectProductor',$configDsFields);
            if ($configDsFields) $this->productorWidget->addFields($configDsFields);
        }

        $this->vars['addedConfig'] = post('addedConfig');
        $this->vars['driverCode'] = $driverCode;
        $this->vars['productorSlug'] = $productorSlug;
        $this->vars['productorBtns'] = $productorDriver->getStaticConfig('methods');
        $this->vars['restartBtn'] = true;
        $this->vars['productorLabel'] = $productorDriver->getStaticConfig('label');
        $this->vars['productorWidget'] = $this->productorWidget;
        //
        return [
            '#productorWidget' => $this->makePartial('prod_form'),
            '#productorModalBtns' => $this->makePartial('btns'),
        ];


        //$vars = $this->controller->ChangeVarsAfter($model, $productor);
    }

    private function getAdditionalConfig($jsonData)
    {
        if (is_string($jsonData)) {
            $decrypted = json_decode(html_entity_decode($jsonData), true);
            //trace_log('json error !!', json_last_error_msg());
            return $decrypted;
        } else {
            return [];
        }
    }

    public function onExecute()
    {
        //trace_log(\Input::all());

        $driverCode = post('driverCode');
        $productorSlug = post('productorSlug');
        $productorHandler = post('handler');

        //
        // $configProductor = $this->controller->updateProductorConfig($this->config->productor);
        //trace_log('configProductor!',$configProductor);

        $additionalConfig = $this->getAdditionalConfig(post('addedConfig'));
        //trace_log('$additionalConfig 2 ',$additionalConfig);
        //trace_log('onSelectProductor',$dsMap);
        //On récupère le driver
        $productorDriver = $this->driverManager->driver($driverCode);
        $postData = post();
        unset($postData['addedConfig']);
        //trace_log('onExecute!',$postData);
        if ($asksData = $postData['productorDataArray']['prod_asks'] ?? false) {
            $askData = Arr::keyBy($asksData, 'b_code');
            $postData['prod_asks'] = $askData;
            unset($postData['productorDataArray']['prod_asks']);
        }
        //On ajoute toutes les données du formulaire ainsi que le modelClass ( autrees champs : modelId, reponseType, driverCode, etc.)
        $allDatas = array_merge($postData,  ['config' => $additionalConfig, 'modelClass' => $this->config->modelClass]);
        //Si on utilise l'importateur de fichier on remplace les données par productorrArray();
        if ($productorDriver->getStaticConfig('use_import_file_widget') ?? false) {
            //trace_log($this->excelWidget->getSaveData());
            $allDatas = array_merge($allDatas,  ['config' => $additionalConfig, 'productorDataArray' => $this->excelWidget->getSaveData()]);
        }
        try {
            $result = $productorDriver->execute($productorSlug, $productorHandler, $allDatas);
            //trace_log('success----------------------------');
            return $this->handleProductorSuccess($result);
        } catch (ValidationException $ex) {
            //trace_log('ValidationException---------------', $ex);
            throw  $ex;
        } catch (\Exception $ex) {
            //trace_log('Exception-------------------------');
            \Log::error($ex);
            return $this->handleProductorError($ex, 500);
        }
    }

    private function handleProductorError($ex)
    {
        $this->vars['btns'] = [];
        $this->vars['restartBtn'] = true;
        $this->vars['message'] = Str::limit($ex, 500);
        return [
            '#productorWidget' => $this->makePartial('error'),
            '#productorModalBtns' => $this->makePartial('btns'),
        ];
    }

    private function handleProductorSuccess($successData)
    {
        //trace_log($successData);
        $this->vars['btns'] = [];
        $this->vars['content'] = [];
        $this->vars['restartBtn'] = true;
        $this->vars['message'] = $successData['message'] ?? 'Processus Terminé';

        if ($closeBtn = $successData['btn'] ?? false) {
            $link = $closeBtn['link'] ?? null;
            if ($link) {
                $closeBtn['link'] =  \Crypt::encrypt($link);;
            }
            $this->vars['closeBtn'] = $closeBtn;
        }
        if ($partial = $successData['partial'] ?? false) {
            $this->vars['content'] = $partial;
        }
        $partialsUpdate = [
            '#productorWidget' => $this->makePartial('success'),
            '#productorModalBtns' => $this->makePartial('btns'),
            '#extraContent' => $this->makePartial('content'),
        ];
        if ($successData['keep_btns'] ?? false) {
            //Si on doit conserver les boutons on  supprime le partial des boutons de la maj 
            unset($partialsUpdate['#productorModalBtns']);
        }
        if ($successData['keep_form'] ?? false) {
            //Si on doit conserver les boutons on  supprime le partial des boutons de la maj 
            unset($partialsUpdate['#productorWidget']);
        }
        return $partialsUpdate;
    }

    public function onCloseAndDownload()
    {
        return \Backend::redirect($this->config->backendUrl . "/download/" . post('link'));
    }

    public function onGoToBo()
    {
        $link = \Crypt::decrypt(post('link'));
        return \Backend::redirect($link);
    }

    public function onOpenLink()
    {
        //trace_log(post('link'));
        $link = \Crypt::decrypt(post('link'));
        //trace_log($link);
        return \Redirect::to($link);
    }

    public function download($cryptedurl)
    {
        $url = \Crypt::decrypt($cryptedurl);
        return \Response::download($url)->deleteFileAfterSend(true);
    }

    public function onCloseAndRefresh()
    {
        return \Redirect::refresh();
    }



    //

    public function createFileImportWidget()
    {
        $config = $this->makeConfig('~/plugins/waka/maatexcel/models/importexcel/productor_config.yaml');
        $config->alias = 'productorData';
        $config->arrayName = 'productorDataArray';
        $config->model = new \Waka\MaatExcel\Models\ImportExcel();
        $widget = $this->makeWidget('Backend\Widgets\Form', $config);
        $widget->bindToController();
        return $widget;
    }

    public function createProductorWidget($configYaml, $targetModel)
    {
        $config = $this->makeConfig($configYaml);
        $config->alias = 'productorData';
        $config->arrayName = 'productorDataArray';
        $config->model = $targetModel;
        $widget = $this->makeWidget('Backend\Widgets\Form', $config);
        $widget->bindToController();
        return $widget;
    }
}
