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
            'prepareEmail' => [
                'label' => 'Envoyer email',
                'handler' => 'prepareEmail',
                'btn' => [],
            ],
            
        ],
    ];

    public function prepareEmail($templateCode, $allDatas): array
    {
        $this->getBaseVars($allDatas);
        $subject = Arr::get($allDatas, 'productorDataArray.subject');
        $tos = Arr::get($allDatas, 'productorDataArray.tos');
        $subject = \Twig::parse($subject, $this->data);
        \Mail::send($templateCode, $this->data, function ($message) use ($tos, $subject) {
            $message->to($tos);
            $message->subject($subject);
        });
        return [
            'message' => 'Mail envoyé avec succès',
        ];
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
