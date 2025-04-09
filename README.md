# Atelier_MOSSetup

Este módulo es parte de los desarrollos de Atelier para Magento 2.

## Instalación

1. Agrega el repositorio a tu proyecto Magento:

```json
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/atelier/magento2-module-mossetup"
  }
]
```

2. Instala vía Composer:

```bash
composer require atelier/module-mossetup:dev-main
```

3. Habilita el módulo:

```bash
bin/magento module:enable Atelier_MOSSetup
bin/magento setup:upgrade
```

## Licencia

MIT
