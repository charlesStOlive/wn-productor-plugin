<?php namespace Waka\Productor;

use Backend;
use Backend\Models\UserRole;
use System\Classes\PluginBase;
use Waka\Productor\Classes\ProductorDriverManager;
use App;

/**
 * Productor Plugin Information File
 */
class Plugin extends PluginBase
{
    public $require = [
        'Waka.Wutils',
    ];
    /**
     * Returns information about this plugin.
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'waka.productor::lang.plugin.name',
            'description' => 'waka.productor::lang.plugin.description',
            'author'      => 'Waka',
            'icon'        => 'icon-leaf'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     */
    public function register(): void
    {
        $this->app->singleton('waka.productor.drivermanager', function ($app) {
            return new ProductorDriverManager;
        });

        $driverManager = App::make('waka.productor.drivermanager');
        $driverManager->registerDriver('mailer', function () {
            return new \Waka\Productor\Classes\Mailer();
        });

    }

    /**
     * Registers the custom Blocks provided by this plugin
     */
    public function registerBlocks(): array
    {
        return [
            'asks_html' => '$/waka/productor/blocks/ask_html.block',
            'ask_images' => '$/waka/productor/blocks/ask_image.block',
        ];
    }

}
