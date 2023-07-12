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
            $drivers[$driverKey] = $driverInstance::getConfig();
        }
        return $drivers;
    }

    public function getAuthorisedDrivers($config, $user)
    {
        $drivers = [];
        $productorConfig = $config->productor;
        $modelClass = $config->modelClass;
        foreach($this->drivers as $driverKey=>$driver) {
            if(array_key_exists($driverKey, $productorConfig['drivers'])) {
                $driverConfig = $productorConfig['drivers'][$driverKey];
                $driverInstance = $driver(); // Ici vous instanciez le driver.
                $productors = 
                $drivers[$driverKey] = [
                    'config' => $driverInstance::getConfig(),
                    'productors' => $driverInstance::getProductors($driverConfig, $modelClass, $user),
                ];
            }
        }
        return $drivers;
    }
}
