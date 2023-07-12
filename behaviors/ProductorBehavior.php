<?php

namespace Waka\Productor\Behaviors;

use Backend\Classes\ControllerBehavior;
use App;
use Str;
use Arr;
use Event;

class ProductorBehavior extends ControllerBehavior
{
    /**
     * @var array Configuration values that must exist when applying the primary config file.
     * - modelClass: Class name for the model
     * - form: Form field definitions
     */
    protected $requiredConfig = ['modelClass', 'productor'];

    /**
     * @var mixed Configuration for this behaviour
     */
    public $productorConfig = 'config_waka.yaml';

    /**
     * @var Backend\Classes\WidgetBase Reference to the widget used for ....
     */
    protected $sendBehaviorWidget;

    /**
     * @var Backend\Classes\WidgetBase Reference to the widget used for ....
     */
    protected $asksWidget;


    /**
     * @var array liste des erreurs remontés par les méthodes ou les evenements.  
     */
    public $errors;

    /**
     * $user = BackendAuth::getUser();
     */
    protected $user;

    /**
     * 
     */
    private $driverManager;


    public function __construct($controller)
    {

        parent::__construct($controller);
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
        Event::listen('controller.btns.action_bar.before_partial', function () {
            return $this->makePartial('btn');;
        });
        $this->driverManager = App::make('waka.productor.drivermanager');
    }

    public function onLauchProductor()
    {
        $drivers = $this->driverManager->getAuthorisedDrivers($this->config, $this->user);
        $drivers = Arr::map($drivers, function ($value, $driverKey) {
            $label = Arr::get($value, 'config.label');
            $data = Arr::get($value, 'productors');
            return [
                'label' => $label,
                'data' => $data
            ];
        });
        $this->vars['drivers'] = $drivers;
        trace_log($drivers);
    }

    public function onSelectProductor()
    {
        $productorId = post('productorId');
        $productorKey = post('productorKey');
        trace_log(post());

        //$vars = $this->controller->ChangeVarsAfter($model, $productor);
    }

    public function ChangeVarsAfter($model, $productor)
    {
        return [];
    }


    public function createSendBehaviorWidget($config)
    {

        $config = $this->makeConfig('$/waka/mailer/models/wakamail/fields_for_mail.yaml');
        $config->alias = 'productorSendWidget';
        $config->arrayName = 'productor_send_array';
        $config->model = new WakaMail();
        $widget = $this->makeWidget('Backend\Widgets\Form', $config);
        $widget->bindToController();
        return $widget;
    }

    public function createSaveToWidget()
    {
        $config = $this->makeConfig('$/waka/mailer/models/wakamail/fields_for_data_mail.yaml');
        $config->alias = 'productorSaveToformWidget';
        $config->arrayName = 'productor_save_to_array';
        $config->model = new WakaMail();
        $widget = $this->makeWidget('Backend\Widgets\Form', $config);
        $widget->bindToController();
        return $widget;
    }
    public function createAskWidget()
    {
        $config = $this->makeConfig('$/waka/wakablocs/models/ask/empty_fields.yaml');
        $config->alias = 'productorAskDataformWidget';
        $config->arrayName = 'productor_asks_array';
        $config->model = new \Waka\WakaBlocs\Models\RuleAsk();
        $widget = $this->makeWidget('Backend\Widgets\Form', $config);
        $widget->bindToController();
        return $widget;
    }
}
