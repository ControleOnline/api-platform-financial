# financial


`composer require controleonline/financial:dev-master`



Create a new fila on controllers:
config\routes\controllers\financial.yaml

```yaml
controllers:
    resource: ../../vendor/controleonline/financial/src/Controller/
    type: annotation      
```

Add to entities:
nelsys-api\config\packages\doctrine.yaml
```yaml
doctrine:
    orm:
        mappings:
           financial:
                is_bundle: false
                type: annotation
                dir: "%kernel.project_dir%/vendor/controleonline/financial/src/Entity"
                prefix: 'ControleOnline\Entity'
                alias: App                             
```          


Add this line on your routes:
config\packages\api_platform.yaml
```yaml          
mapping   :
    paths: ['%kernel.project_dir%/src/Entity','%kernel.project_dir%/src/Resource',"%kernel.project_dir%/vendor/controleonline/financial/src/Entity"]        
```          
