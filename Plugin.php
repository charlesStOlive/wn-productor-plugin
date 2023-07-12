<?php namespace Waka\Productor;

use Backend;
use Backend\Models\UserRole;
use System\Classes\PluginBase;
use Waka\Productor\Classes\ProductorDriverManager;

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

    }

    /**
     * Boot method, called right before the request route.
     */
    public function boot(): void
    {

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

        return [
            'waka.productor.some_permission' => [
                'tab' => 'waka.productor::lang.plugin.name',
                'label' => 'waka.productor::lang.permissions.some_permission',
                'roles' => [UserRole::CODE_DEVELOPER, UserRole::CODE_PUBLISHER],
            ],
        ];
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
