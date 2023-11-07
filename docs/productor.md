# wn-productor-plugin
Ce plugin permet de lier des modèles de production avec des modèles et des controller. 

## instalation 
Ce plugin va installer s'ils n'existent pas encore waka/wn-ds-plugin et waka/wn-wutils-plugin.

```
composer require waka/wn-productor-plugin
```


## utilisation dans un controller
Ajouter le behavior dans implement
```php
class MaClasse extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        //
        \Waka\Productor\Behaviors\ProductorBehavior::class,
    ];
    //...reste de la classe
}
``` 
1. Créer/Mettre a jours le fichier config_waka dans le dossier liée au controller :  vendor/plugin/controller/maclasse/config_waka.yaml
2. ajouter une config  productor ( et modelClass + backendUrl) si vous créez le fichier
```
modelClass: Wcli\Crm\Models\Client
backendUrl: wcli/crm/clients

productor:
    models: 
        wcli.tarificateur::*.projet.*:
            # drivers: mjmler,worder
            # dsMap: main
        excelerRelationExporter:
        excelerRelationImporter:
```

## Création d'un driver 
 Un driver permet de lier un plugin ou une classe productrice avec productor. 
 ### Important : 
 Pour fonctionner, un plugin proposant une classe productrice doit avoir : 
1. Un modèle de production doit avoir les champs suivants : 
   1.  un champs slug ( le code du template à exploiter )
   2.  Un  champ name (l'intitulé)  
   3.  Si besoin étendre avec le trait ds
2. Le modèle doit fournir une classe de production
