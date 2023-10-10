<?php

namespace Waka\Productor\Classes;

use \Waka\Productor\Classes\Abstracts\BaseProductor;
use Closure;
use Arr;
use ApplicationException;

class Mailer extends BaseProductor
{

    protected static $config = [
        'label' => 'waka.productor::lang.driver.mailer.label',
        'icon' => 'icon-envelope',
        'description' => 'waka.productor::lang.driver.mailer.description',
        'productorModel' => \System\Models\MailTemplate::class,
        'productorFilesRegistration' =>  'registerMailTemplates',
        'productor_yaml_config' => '~/plugins/waka/productor/models/mailer/productor_config.yaml',
        'noProductorBdd'=> true,
        'methods' => [
            'sendEmail' => [
                'label' => 'Envoyer email',
                'handler' => 'sendEmail',
                'btn' => [],
            ],
            
        ],
    ];

    public function execute($templateCode, $productorHandler, $allDatas): array
    {

        //trace_log('Mailer : Execute-------------');
        $this->getBaseVars($allDatas);
        $subject = Arr::get($allDatas, 'productorDataArray.subject');
        $tos = Arr::get($allDatas, 'productorDataArray.tos');
        $subject = \Twig::parse($subject, $this->data);
        //trace_log('allDatas!!',$allDatas);
        //trace_log($this->data);
        if ($productorHandler == "sendEmail") {
            \Mail::send($templateCode, $this->data, function ($message) use ($tos, $subject) {
                $message->to($tos);
                $message->subject($subject);
            });
            return [
                'message' => 'Mail envoyé avec succès',
            ];
        } else {
            throw new ApplicationException('Mailer accepte comme handler uniquement sendEmail ');
        }
    }

    /**
     * Instancieation de la class creator
     *
     */


    public static function updateFormwidget($slug, $formWidget, $config = [])
    {
        $productorModel = \System\Models\MailTemplate::findOrMakeTemplate($slug);
        if($productorModel) {
            $formWidget->getField('subject')->value = $productorModel->subject;
        } else {
            $formWidget->getField('subject')->value = $config['subject'] ?? 'Un sujet';;
        }
        return $formWidget;
    }
}
