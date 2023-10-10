<?php

namespace Waka\Productor\Classes;

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
        foreach ($this->drivers as $driverKey => $driver) {
            $driverInstance = $driver(); // Ici vous instanciez le driver.
            $drivers[$driverKey] = $driverInstance::getStaticConfig();
        }
        return $drivers;
    }

    public function getAuthorisedDrivers($config, $forIndex = false)
    {
        $drivers = [];
        $productorConfig = null;
        if ($forIndex) {
            $productorconfig = $config->productorIndex;
        } else {
            $productorconfig = $config->productor;
        }
        $globalDsMap = $config->dsMap ?? null;
        $productorModels = $productorconfig['models'] ?? null;
        //
        foreach ($productorModels as $modelsKey => $modelsConfig) {
            if(!$modelsConfig) $modelsConfig = [];
            $modelsDsMap = $modelsConfig['dsMap'] ?? null;
            if($globalDsMap && ! $modelsDsMap) {
                $modelsDsMap = $globalDsMap;
            }
            $driverReceived = $this->getModelsFromDrivers($modelsKey, $modelsConfig);
            $driverKeyReceived = array_key_first($driverReceived);
            if ($drivers[$driverKeyReceived] ?? false) {
                //Le driver a déjà été importé par un autre modèle nous mergons recursivement uniquement les infos de modes. 
                $drivers[$driverKeyReceived]['productorModels'] = array_merge($drivers[$driverKeyReceived]['productorModels'], $driverReceived[$driverKeyReceived]['productorModels']);
            } else {
                $drivers = array_merge($drivers, $driverReceived);
            }
        }
        // trace_log($drivers);
        return $drivers;
    }

    private function getModelsFromDrivers($modelsKey, $modelsConfig)
    {
        $driversToReturn = [];
        $allDrivers = [];
        
        $specificDrivers = $modelsConfig['drivers'] ?? null;
        $specificDrivers = trim($specificDrivers);
        //trace_log('specificDrivers : '.$specificDrivers, !empty($specificDrivers));
        if($specificDrivers && !is_array($specificDrivers)) {
            $specificDrivers = explode(',',$specificDrivers);
        }
        //trace_log("$modelsKey",$specificDrivers);
        if ($specificDrivers) {
            foreach ($this->drivers as $key => $driver) {
                // trace_log('key : '.$key, $specificDrivers);
                //Recherche si la clef existe dans les clefs de config de drivers. 
                if (in_array($key, $specificDrivers)) {
                    // trace_log('cette clef existe : '.$key);
                    $allDrivers[$key] = $driver;
                } else {
                    // trace_log('cette clef existe PAS : '.$key);
                }
            }
        } else {
            $allDrivers = $this->drivers;
        }
        foreach ($allDrivers as $driverKey => $driver) {
            $driverInstance = $driver(); // Ici vous instanciez le driver.
            //trace_log('driverKey', $driverKey);
            $models =  $driverInstance::getModels($modelsKey, $modelsConfig);
            if (!empty($models)) {
                $config = $driverInstance::getStaticConfig();
                $driversToReturn[$driverKey] = [
                    'config' => $config,
                    'productorModels' => $models,
                ];
            }
        }
        return $driversToReturn;
    }
}
