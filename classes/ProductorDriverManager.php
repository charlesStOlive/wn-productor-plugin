<?php namespace Waka\Productor\Classes;

class ProductorDriverManager
{
    protected $drivers = [];

    public function registerDriver(string $name, $resolver)
    {
        $this->drivers[$name] = $resolver;
    }

    public function driver(string $name)
    {
        if (!isset($this->drivers[$name])) {
            throw new \InvalidArgumentException("Driver [{$name}] is not registered.");
        }

        // Retourne l'instance du driver demandé.
        return call_user_func($this->drivers[$name]);
    }

    // Nouvelle méthode pour obtenir tous les drivers
    public function getAllDrivers()
    {
        $drivers = [];
        foreach($this->drivers as $driverKey=>$driver) {
            $driverInstance = $driver(); // Ici vous instanciez le driver.
            $drivers[$driverKey] = $driverInstance::getStaticConfig();
        }
        return $drivers;
    }

    public function getAuthorisedDrivers($config, )
    {
        $drivers = [];
        $productorConfig = $config->productor;
        if(!isset($productorConfig['drivers'])) {
            throw new \ApplicationException('Il manque  dans productor->drivers dans config_waka');
        }
        $modelClass = $config->modelClass;
        foreach($this->drivers as $driverKey=>$driver) {
            if(array_key_exists($driverKey, $productorConfig['drivers'])) {
                $driverConfig = $productorConfig['drivers'][$driverKey];
                $globalPermissions = $productorConfig['permissions'] ?? [];
                $driverInstance = $driver(); // Ici vous instanciez le driver.
                $drivers[$driverKey] = [
                    'config' => $driverInstance::getStaticConfig(),
                    'productors' => $driverInstance::getProductors($driverConfig, $globalPermissions),
                ];
            }
        }
        return $drivers;
    }
    
}
