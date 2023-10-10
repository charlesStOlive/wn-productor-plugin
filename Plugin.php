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
     * Boot method, called right before the request route.
     */
    public function boot(): void
    {

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

    /**
     * Registers any frontend components implemented in this plugin.
     */
    public function registerComponents(): array
    {
        return []; // Remove this line to activate

        return [
            'Waka\Productor\Components\MyComponent' => 'myComponent',
        ];
    }

    /**
     * Registers any backend permissions used by this plugin.
     */
    public function registerPermissions(): array
    {
        return []; // Remove this line to activate
    }

    /**
     * Registers backend navigation items for this plugin.
     */
    public function registerNavigation(): array
    {
        return []; // Remove this line to activate

        return [
            'productor' => [
                'label'       => 'waka.productor::lang.plugin.name',
                'url'         => Backend::url('waka/productor/mycontroller'),
                'icon'        => 'icon-leaf',
                'permissions' => ['waka.productor.*'],
                'order'       => 500,
            ],
        ];
    }
}
