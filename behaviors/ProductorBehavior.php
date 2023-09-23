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
        $this->addCss('css/productor.css', 'Waka.Productor');
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
        Event::listen('controller.btns.action_bar.before_partial', function ($params) {
            if ($params->context == 'update') return $this->makePartial('controller_btn');
        });
        $this->driverManager = App::make('waka.productor.drivermanager');
        //
        $this->excelWidget = $this->createFileImportWidget();
    }

    public function onLauchProductor()
    {
        $configProductor = $this->controller->updateProductorConfig($this->config->productor);
        if (!empty($configProductor)) {
            $this->config->productor = $configProductor;
        }
        $drivers = $this->driverManager->getAuthorisedDrivers($this->config);

        $drivers = Arr::map($drivers, function ($value, $driverKey) {
            $label = Arr::get($value, 'config.label');
            $data = Arr::get($value, 'productors');
            return [
                'label' => $label,
                'productors' => $data
            ];
        });
        $manualHandlers = $this->getManualHandlers();
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
        return $this->config->productor['handlers'] ?? [];
    }

    public function updateProductorConfig($productorConfig)
    {
        return $productorConfig;
    }

    public function onSelectProductor()
    {
        //trace_log(post());
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
        $driverConfig = $productorDriver->getConfig();
        $dsMap = $this->getDsMapFromConfig($driverCode);
        //trace_log($dsMap);
        //
        $targetModel = $this->config->modelClass::find($modelId);
        if ($driverConfig['use_import_file_widget'] ?? false) {
            $this->productorWidget = $this->excelWidget;
        } else {
            $this->excelWidget = null;
            $this->productorWidget = $this->createProductorWidget($driverConfig['productor_yaml_config'], $targetModel);
            $this->productorWidget = $productorDriver::updateFormwidget($productorSlug, $this->productorWidget, $dsMap);
            $configDsFields = $targetModel->dsGetParamsConfig($dsMap);
            if ($configDsFields) $this->productorWidget->addFields($configDsFields);
        }

        $this->vars['driverCode'] = $driverCode;
        $this->vars['productorSlug'] = $productorSlug;
        $this->vars['productorBtns'] = $driverConfig['methods'];
        $this->vars['restartBtn'] = true;
        $this->vars['productorLabel'] = $driverConfig['label'];
        $this->vars['productorWidget'] = $this->productorWidget;
        //
        return [
            '#productorWidget' => $this->makePartial('prod_form'),
            '#productorModalBtns' => $this->makePartial('btns'),
        ];


        //$vars = $this->controller->ChangeVarsAfter($model, $productor);
    }

    private function getDsMapFromConfig($driverCode)
    {
        $dsMap = $configProductor['dsMap'] ?? null;
        //trace_log('config productor[driverCode]!',$this->config->productor);
        if ($driverDsMap = $this->config->productor['drivers'][$driverCode]['dsMap'] ?? false) {
            $dsMap = $driverDsMap;
        }
        return $dsMap;
    }

    public function onExecute()
    {
        //trace_log(\Input::all());
        //trace_log(post());
        $driverCode = post('driverCode');
        $productorSlug = post('productorSlug');
        $productorHandler = post('handler');
        //
        $configProductor = $this->controller->updateProductorConfig($this->config->productor);
        //trace_log('configProductor!',$configProductor);
        $dsMap = $this->getDsMapFromConfig($driverCode);
        //On récupère le driver
        $productorDriver = $this->driverManager->driver($driverCode);
        $driverConfig = $productorDriver->getConfig();
        //On ajoute toutes les données du formulaire ainsi que le modelClass ( autrees champs : modelId, reponseType, driverCode, etc.)
        $allDatas = array_merge(post(), ['modelClass' => $this->config->modelClass, 'dsMap' => $dsMap]);
        //Si on utilise l'importateur de fichier on remplace les données par productorrArray();
        if ($driverConfig['use_import_file_widget'] ?? false) {
            //trace_log($this->excelWidget->getSaveData());
            $allDatas = array_merge($allDatas, ['productorDataArray' => $this->excelWidget->getSaveData()]);
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
