<?php

namespace Waka\Productor\Behaviors;

use Backend\Classes\ControllerBehavior;
use App;
use Str;
use Arr;
use Event;
use ValidationException;
use ApplicationException;

class ProductorIndexBehavior extends ControllerBehavior
{
    /**
     * @var array Configuration values that must exist when applying the primary config file.
     * - modelClass: Class name for the model
     * - form: Form field definitions
     */
    protected $requiredConfig = ['modelClass', 'backendUrl', 'productorIndex'];

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
        Event::listen('waka_controller.index_bar.after_partial', function () {
            if($this->hasData()) {
                return $this->makePartial('controller_btn');
            }
            
        });
        $this->driverManager = App::make('waka.productor.drivermanager');
        //
        $this->excelWidget = $this->createFileImportWidget();
    }

    public function hasData() {
        $models = $this->config->productorIndex['models'] ?? false;
        $handlers = $this->config->productorIndex['handlers'] ?? false;
        return $models || $handlers;

    }

    public function onLauchProductorIndex()
    {
        $checkedIds = post('checked', []);
        $countCheck = count($checkedIds);
        \Session::put('waka.productor.productorindex.checkedIds', $checkedIds);
        //
        $configProductor = $this->controller->updateProductorIndexConfig($this->config->productorIndex);
        if (!empty($configProductor)) {
            $this->config->productorIndex = $configProductor;
        }
        $drivers = $this->driverManager->getAuthorisedDrivers($this->config, true);
        $drivers = Arr::map($drivers, function ($value, $driverKey) {
            $label = Arr::get($value, 'config.label');
            $icon = Arr::get($value, 'config.icon');
            $data = Arr::get($value, 'productorModels');
            return [
                'label' => $label,
                'icon' => $icon,
                'productorModels' => $data
            ];
        });
        $manualHandlers = $this->getManualHandlers();
        if (!empty($manualHandlers)) {
            $drivers['handlers'] = $manualHandlers;
        }
        //trace_log($drivers);
        $this->vars['countCheck'] = $countCheck;
        $this->vars['modelId'] = post('modelId');
        $this->vars['drivers'] = $drivers;
        return $this->makePartial('popup');
    }

    

    public function onSelectProductorIndex()
    {
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
        //trace_log($dsMap);
        if ($productorDriver->getStaticConfig('use_import_file_widget') ?? false) {
            $this->productorWidget = $this->excelWidget;
        } else {
            $this->excelWidget = null;
            $this->productorWidget = $this->createProductorWidget($productorDriver->getStaticConfig('productor_yaml_config'));
            $this->productorWidget = $productorDriver::updateFormwidget($productorSlug, $this->productorWidget,$additionalConfig);
            
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

    protected function getManualHandlers()
    {
        return $this->config->productorIndex['handlers'] ?? [];
    }

    public function updateProductorIndexConfig($productorIndexConfig)
    {
        return $productorIndexConfig;
    }

    private function getAdditionalConfig($jsonData)
    {
        if(is_string($jsonData)) {
            $decrypted = json_decode(html_entity_decode($jsonData), true);
            //trace_log('json error !!', json_last_error_msg());
            return $decrypted;
        } else {
            return [];
        }
        
    }

    public function onIndexExecute()
    {
        //trace_log(\Input::all());
        $driverCode = post('driverCode');
        $productorSlug = post('productorSlug');
        $productorHandler = post('handler');
        //
        $configProductor = $this->controller->updateProductorIndexConfig($this->config->productorIndex);
        $additionalConfig = $this->getAdditionalConfig(post('addedConfig'));
        //On récupère le driver
        $productorDriver = $this->driverManager->driver($driverCode);
        $postData = post();
        //trace_log('onExecute!',$postData);
        if($asksData = $postData['productorDataArray']['prod_asks'] ?? false) {
            $askData = Arr::keyBy($asksData, 'b_code');
            $postData['prod_asks'] = $askData;
            unset($postData['productorDataArray']['prod_asks']);
        }
        //On ajoute toutes les données du formulaire ainsi que le modelClass ( autrees champs : modelId, reponseType, driverCode, etc.)
        $allDatas = array_merge($postData, ['config' => $additionalConfig, 'modelClass' => $this->config->modelClass]);
        //Si on utilise l'importateur de fichier on remplace les données par productorrArray();
        if ($productorDriver->getStaticConfig('use_import_file_widget') ?? false) {
            //trace_log($this->excelWidget->getSaveData());
            $allDatas = array_merge($allDatas, ['productorDataArray' => $this->excelWidget->getSaveData()]);
        }
        try {
            $result = $productorDriver->execute($productorSlug, $productorHandler, $allDatas);
            //trace_log('success----------------------------');
            return $this->handleProductorIndexSuccess($result);
        } catch (ValidationException $ex) {
            //trace_log('ValidationException---------------', $ex);
            throw  $ex;
        } catch (\Exception $ex) {
            //trace_log('Exception-------------------------');
            \Log::error($ex);
            return $this->handleProductorIndexError($ex, 500);
        }
    }

    private function handleProductorIndexError($ex)
    {
        $this->vars['btns'] = [];
        $this->vars['restartBtn'] = true;
        $this->vars['message'] = Str::limit($ex, 500);
        return [
            '#productorWidget' => $this->makePartial('error'),
            '#productorModalBtns' => $this->makePartial('btns'),
        ];
    }

    private function handleProductorIndexSuccess($successData)
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
            return [
                '#productorWidget' => $this->makePartial('success'),
                '#productorModalBtns' => $this->makePartial('btns'),
                '#extraContent' => $this->makePartial('content'),
            ];
        }
        if ($partial = $successData['partial'] ?? false) {
            $this->vars['content'] = $partial;
            return [
                '#extraContent' => $this->makePartial('content'),
            ];
        }
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

    public function createProductorWidget($configYaml)
    {
        $config = $this->makeConfig($configYaml);
        $config->alias = 'productorData';
        $config->arrayName = 'productorDataArray';
        $config->model = new \Waka\MaatExcel\Models\ExportExcel();
        $widget = $this->makeWidget('Backend\Widgets\Form', $config);
        $widget->bindToController();
        return $widget;
    }
}
