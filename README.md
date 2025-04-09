# Módulo de utilidades para alimentar Magento 2.4.7
Este módulo proporciona herramientas para crear datos de prueba para un catálogo de Magento 2, incluyendo categorías y diferentes tipos de productos.

## Funcionalidades
- Creación de categorías a partir de JSON
- Creación de productos de diferentes tipos: 
- Productos simples 
- Productos virtuales 
- Productos descargables 
- Productos configurables 
- Productos agrupados 
- Productos bundle
- Comandos para limpiar, crear y añadir datos

## Instalación
1. Copiar el módulo en la carpeta `app/code/Atelier/MosSetup`

2. Habilitar el módulo: 

```bash 
bin/magento module:enable Atelier_MosSetup 
```

3. Actualizar la base de datos: 

```bash 
bin/magento setup:upgrade 
```

4. Limpiar la caché: 

```bash 
bin/magento cache:clean 
```

## Uso
### Configurar la tienda

```bash
bin/magento atelier:setup:store
```

Este comando pemite configurar una tienda básica: idioma, países, divisa, forma de pago, forma de envío, etc (ver código)Se pueden contestar preguntas o cargar un fichero previo.Borrar caché una vez aplicado para ver los cambios.

```bash
bin/magento atelier:setup:atributo
```

Este comando asegura que existen los atributos size y color, que tienen opciones, y que están disponibles para ser usados en configurables.Importante: se debe ejecutar antes que el resto de comandos.

### Crear datos de catálogo (y eliminar datos existentes)

```bash
bin/magento atelier:fixture:borra-categoria --todo
```

Borra todas las categorías. Tiene deprecaciones (registry) porque no funciona con Repository.

```bash
bin/magento atelier:fixture:create-categoria --source=json --file=var/sync/import/categorias.json
```
Crea categorias a partir de un JSON en un fichero físico.

```bash
bin/magento atelier:fixture:borra-producto --todo
```

Borra todos los productos.

```bash
bin/magento atelier:fixture:crea-producto
```

Crea productos de todos los tipos excepto configurable.

```bash
bin/magento atelier:fixture:crea-configurable 2
```
Este comando crea varios configurables

### Añadir datos de catálogo a los existentes
```bash
bin/magento atelier:fixture:add-producto 
```

Este comando añade nuevos productos sin eliminar los existentes.

### Generar clientes de prueba y pedidos
```bash
bin/magento atelier:fixture:crea-cliente
```

```bash
bin/magento atelier:fixture:crea-pedido 
```

## Configuración
Puedes modificar el número de categorías y productos a crear editando las constantes en los archivos.

```php
const PRODUCT_COUNT_PER_TYPE = 2; // Número de productos de cada tipo
```

## Shipping
Nota: para que se aplique la tablerate, debe estar activo el método por backend o config.php. No funciona aplicarlo por código.


# Import shipping rates from a CSV file

```bash
bin/magento atelier:setup:shipping --file=var/shipping_rates.csv
```
Importa las rates de tipo package_value_with_discount (gastos de envío según importe del carrito).

Si se desea modificar el tipo, es necesario modificar la cabecera del fichero y opción en importador:
- package_weight
- package_qty
s- package_value_with_discount 