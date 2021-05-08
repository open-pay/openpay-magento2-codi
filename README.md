# Openpay-Magento2-CoDi®

Módulo para pagos vía CoDi® con Openpay para Magento2 (v2.4.2)


## Instalación

Ir a la carpeta raíz del proyecto de Magento y seguir los siguiente pasos:

```bash    
composer require openpay/magento2-codi:1.0.*
php bin/magento module:enable Openpay_Codi --clear-static-content
php bin/magento setup:upgrade
php bin/magento cache:clean
```


## Actualización
En caso de ya contar con el módulo instalado y sea necesario actualizar, seguir los siguientes pasos:

```bash
composer clear-cache
composer update openpay/magento2-codi
bin/magento setup:upgrade
php bin/magento cache:clean
```

## Configuración
Para configurar el módulo desde el panel de administración de la tienda diríjase a: Stores > Configuration > Sales > Payment Methods
