<?php

declare(strict_types=1);

namespace Atelier\MOSSetup\Model;

use Atelier\MOSSetup\Logger\CustomLogger;
use Atelier\MOSSetup\Helper\SecureContextExecutor;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductLinkRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Downloadable\Api\LinkRepositoryInterface;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as OptionsFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as OptionFactory;
use Magento\Eav\Model\Config;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Exception\StateException;

//MSI
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;

class FashionProductManager
{
    private const ATTRIBUTE_TYPE = 4;
    
    // Categorías de productos de moda
    private const FASHION_CATEGORIES = [
        'tops' => ['Camiseta', 'Camisa', 'Blusa', 'Polo', 'Jersey', 'Sudadera'],
        'outerwear' => ['Chaqueta', 'Cazadora', 'Abrigo', 'Chaleco', 'Impermeable', 'Anorak', 'Americana'],
        'bottoms' => ['Pantalón', 'Vaquero', 'Bermuda', 'Short', 'Falda'],
        'dresses' => ['Vestido', 'Mono', 'Peto'],
        'sportswear' => ['Chándal'],
        'sleepwear' => ['Pijama', 'Bata', 'Albornoz'],
        'underwear' => ['Ropa interior', 'Sujetador', 'Braga', 'Calzoncillo', 'Bóxer'],
        'legwear' => ['Medias', 'Pantis', 'Leotardos', 'Calcetines'],
        'accessories' => ['Guantes', 'Bufanda', 'Gorro', 'Sombrero', 'Pañuelo', 'Corbata', 'Pajarita', 'Cinturón']
    ];
    
    // Añadir diccionarios para materiales, estilos, temporadas y características
    private const MATERIALS = [
        'algodón', 'lino', 'seda', 'lana', 'poliéster', 'viscosa', 'tencel',
        'tela vaquera', 'pana', 'punto', 'nylon', 'terciopelo', 'cuero',
        'ante', 'cachemira', 'lycra', 'modal',
        'elastano', 'acrílico', 'rayón', 'bambú', 'neopreno', 
        'gasa', 'encaje', 'tweed', 'jacquard', 'chiffón', 'organza',  
        'popelina', 'microfibra', 'gabardina', 'sarga', 'tartan', 
    ];
    
    private const STYLES = [
        'casual', 'elegante', 'deportivo', 'bohemio', 'vintage', 'minimalista',
        'urbano', 'clásico', 'moderno', 'oversize', 'slim fit', 'regular fit',
        'business', 'romantic', 'streetwear', 'rockero', 'preppy', 'grunge',
        'punk', 'gótico', 'náutico', 'militar', 'artsy', 'ethnic', 'coquette'
    ];
    
    private const SEASONS = [
        'Primavera/Verano', 'Otoño/Invierno', 'Todo el año'
    ];
    
    private const CHARACTERISTICS = [
        'sostenible', 'transpirable', 'stretch', 'impermeable', 'antiarrugas',
        'de secado rápido', 'térmica', 'ligera', 'ultra ligera', 'de alto rendimiento',
        'orgánica', 'hipoalergénica', 'resistente al agua', 'resistente al viento',
        'repelente al agua', 'antibacteriana', 'antimanchas', 'sin plancha',
        'resistente al desgaste', 'con protección uv', 'sin costuras', 'reversible',
        'compresiva', 'ajuste cómodo', 'con forro', 'acolchada', 'con bolsillos ocultos',
        'con capucha', 'plegable'
    ];
    
    private const ADJECTIVES = [
        'esencial', 'premium', 'básico', 'exclusivo', 'moderno', 'clásico',
        'cotidiano', 'lujoso', 'especial', 'limitado', 'moderno', 'icónico',
        'sofisticado', 'elegante', 'urbano', 'estiloso', 'contemporáneo', 'atemporal',
        'refinado', 'relajado', 'versátil', 'casual', 'minimalista', 'único',
        'funcional', 'original', 'ecológico', 'natural', 'vanguardista',
        'irreverente', 'delicado', 'audaz'
    ];

    public function __construct(
        private readonly ProductInterfaceFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ProductLinkRepositoryInterface $productLinkRepository,
        private readonly LinkRepositoryInterface $linkRepository,
        private readonly ExtensionAttributesFactory $extensionAttributesFactory,
        private readonly AttributeRepositoryInterface $attributeRepository,
        private readonly OptionsFactory $optionsFactory,
        private readonly \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        private readonly SecureContextExecutor $secureContextExecutor,
        private readonly CustomLogger $logger,
        private readonly AttributeOptionManagementInterface $attributeOptionManagement,
        private readonly AttributeSetCollectionFactory $attributeSetCollectionFactory,
        private readonly SourceItemInterfaceFactory $sourceItemFactory,
        private readonly SourceItemsSaveInterface $sourceItemsSave,
        private readonly OptionFactory $optionFactory,
        private readonly Config $eavConfig,
        private readonly ProductResource $productResource,
        private readonly array $configurableAttributes = ['color', 'size'],
        
    ) {}
    
    /**
     * Devuelve los datos de stock del producto maestro
     * 
     * @return array Datos de stock del producto maestro
     */
    private function getMasterStockData(): array
    {
        return [
            'use_config_manage_stock' => 1,
            'is_in_stock' => 1,
            'manage_stock' => 0
        ];
    }

    /**
     * Genera un nombre de producto de moda aleatorio
     * 
     * @return array Datos del producto con nombre, tipo, material, etc.
     */
    private function generateFashionProductData(): array
    {
        // Seleccionar una categoría aleatoria
        $categoryGroup = array_rand(self::FASHION_CATEGORIES);
        $categoryItems = self::FASHION_CATEGORIES[$categoryGroup];
        $productType = $categoryItems[array_rand($categoryItems)];
        
        // Seleccionar un material aleatorio
        $material = self::MATERIALS[array_rand(self::MATERIALS)];
        
        // Seleccionar un estilo aleatorio
        $style = self::STYLES[array_rand(self::STYLES)];
        
        // Seleccionar una temporada aleatoria
        $season = self::SEASONS[array_rand(self::SEASONS)];
        
        // Seleccionar características aleatorias (0-2)
        $characteristicsCount = mt_rand(0, 2);
        $shuffledCharacteristics = self::CHARACTERISTICS;
        shuffle($shuffledCharacteristics);
        $characteristics = array_slice($shuffledCharacteristics, 0, $characteristicsCount);
        
        // Seleccionar un adjetivo aleatorio
        $adjective = self::ADJECTIVES[array_rand(self::ADJECTIVES)];
        
        // Construir nombre del producto (con formato variable)
        $nameFormats = [
            "$productType $adjective de $material",
            "$productType $style de $material",
            "$productType de $material $style",
            "$productType $style $adjective de $material",
            "$productType $adjective $style de $material",
        ];
        
        $productName = $nameFormats[array_rand($nameFormats)];
        
        // Generar descripción corta
        $shortDescription = "Este $productType de $material es perfecto para cualquier ocasión. ";
        $shortDescription .= "Con un estilo $style, es ideal para la temporada $season.";
        
        if (!empty($characteristics)) {
            $shortDescription .= " Características: " . implode(', ', $characteristics) . ".";
        }
        
        // Generar descripción larga
        $longDescription = "<p><strong>$productName</strong></p>";
        $longDescription .= "<p>Descubre nuestro exclusivo $productType de $material con un estilo $style único. ";
        $longDescription .= "Diseñado pensando en la comodidad y el estilo, este $productType es perfecto para la temporada $season.</p>";
        
        if (!empty($characteristics)) {
            $longDescription .= "<p><strong>Características:</strong></p><ul>";
            foreach ($characteristics as $characteristic) {
                $longDescription .= "<li>$characteristic</li>";
            }
            $longDescription .= "</ul>";
        }
        
        $longDescription .= "<p><strong>Material:</strong> $material</p>";
        $longDescription .= "<p><strong>Estilo:</strong> $style</p>";
        $longDescription .= "<p><strong>Temporada:</strong> $season</p>";
        $longDescription .= "<p>Instrucciones de lavado: Lavar a máquina a 30°C. No usar lejía. No secar en secadora.</p>";
        
        // Generar el código SKU base
        $skuPrefix = substr(transliterator_transliterate('Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; Upper()', str_replace(' ', '', $productType)), 0, 3);
        $skuMaterial = substr(transliterator_transliterate('Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; Upper()', str_replace(' ', '', $material)), 0, 3);
        $skuStyle = substr(transliterator_transliterate('Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; Upper()', str_replace(' ', '', $style)), 0, 3);
        $skuBase = $skuPrefix . $skuMaterial . $skuStyle;
        
        return [
            'name' => $productName,
            'sku_base' => $skuBase,
            'type' => $productType,
            'material' => $material,
            'style' => $style,
            'season' => $season,
            'characteristics' => $characteristics,
            'short_description' => $shortDescription,
            'description' => $longDescription,
            'category_group' => $categoryGroup
        ];
    }

    // Funciones para los configurables
    public function createConfigurableProducts(int $count) {

        $this->secureContextExecutor->execute(function () use ($count): void {
            
            $this->logger->info('[FashionProductManager] Entro en helper', ['mensaje' => 'Empezamos  crear configurables...']);

            // Encontrar un conjunto de atributos válido
            $attributeSetId = $this->getDefaultSet();
            
            if (!$attributeSetId) {
                throw new LocalizedException(__('[FashionProductManager] No se encontró el conjunto de atributos válido'));
            }
            
            $this->logger->info(sprintf('[FashionProductManager] Usando conjunto de atributos ID: %d', $attributeSetId));
            
            // Obtener opciones para cada atributo (se hace una sola vez para reutilizar)
            $attributesData = $this->getConfigurableAttributesData();
            
            // Bucle para crear el número solicitado de productos configurables
            for ($i = 1; $i <= $count; $i++) {
                
                // Lo hago dentro de bucle para que sea aleatorio
                $attributeOptions = $this->getAttributeOptions();
                
                $this->logger->info(sprintf('[FashionProductManager] Creando producto configurable %d de %d.', $i, $count));
                
                // Generar datos del producto de moda
                $fashionProductData = $this->generateFashionProductData();
                
                // Crear el producto configurable
                $configurableProduct = $this->createConfigurableProduct($i, $attributeSetId, $fashionProductData);
                
                // Crear productos simples (variaciones)
                $simpleProducts = $this->createVariations($attributeOptions, $configurableProduct->getSku(), $attributeSetId, $fashionProductData);
                
                // Asociar productos simples al configurable
                $this->associateProducts($configurableProduct, $simpleProducts, $attributesData);
                
                $this->logger->info(sprintf(
                    '[FashionProductManager] Producto configurable "%s" (%s) creado con %d variaciones.',
                    $configurableProduct->getName(),
                    $configurableProduct->getSku(),
                    count($simpleProducts)
                ));
            } 

        }); 
    }  

    /**
     * Asigna el AttributeSet Default
     * 
     * @return int|null
     */
    private function getDefaultSet(): ?int
    {
        $entityTypeId = $this->eavConfig->getEntityType(\Magento\Catalog\Model\Product::ENTITY)->getEntityTypeId();
        return (int) $this->eavConfig->getEntityType($entityTypeId)->getDefaultAttributeSetId();
    }

    /**
     * Crear el producto configurable base
     *
     * @param int $index Índice del producto para diferenciarlos
     * @param int $attributeSetId ID del conjunto de atributos a usar
     * @param array $fashionProductData Datos del producto de moda
     * @return ProductInterface
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    private function createConfigurableProduct(int $index, int $attributeSetId, array $fashionProductData): ProductInterface
    {
        /** @var Product $product */
        $product = $this->productFactory->create();
        
        $uniqueId = substr(uniqid(), -6);
        $productName = $fashionProductData['name'];
        $skuBase = $fashionProductData['sku_base'];
        
        // Generar SKU único para el configurable
        $sku = $skuBase . '-' . $uniqueId;
        
        $product->setTypeId(Configurable::TYPE_CODE)
            ->setAttributeSetId($attributeSetId)
            ->setName($productName)
            ->setSku($sku)
            ->setUrlKey($this->generateUrlKey($productName, $uniqueId))
            ->setStatus(Status::STATUS_ENABLED)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStockData($this->getMasterStockData())
            ->setPrice(0.0)
            ->setShortDescription($fashionProductData['short_description'])
            ->setDescription($fashionProductData['description']);

        // Añadir atributos personalizados si existen
        $product->setData('material', $fashionProductData['material']);
        $product->setData('estilo', $fashionProductData['style']);
        $product->setData('temporada', $fashionProductData['season']);
        
        // Para que se puedan asociar las combinaciones por los atributos configurables
        foreach ($this->configurableAttributes as $attributeCode) {
            $product->setData($attributeCode, null);
        }

        if (($categoryId = $this->getCategoryIdByProductType($fashionProductData['type'], $fashionProductData['category_group'])) !== null) {
            $product->setCategoryIds([$categoryId]);
        }

        return $this->productRepository->save($product);
    }

    /**
     * Obtiene la categoría adecuada basada en el tipo de producto de moda y el género
     * 
     * @param string $productType Tipo de producto de moda (ej: 'Camisa', 'Pantalón')
     * @param string $categoryGroup Grupo de categoría (ej: 'tops', 'bottoms')
     * @return int|null ID de la categoría o null si no se encuentra
     */
    private function getCategoryIdByProductType(string $productType, string $categoryGroup): ?int
    {
        // Definir el mapeo correcto según la estructura de categorías mostrada en las imágenes
        $categoryMapping = [
            // Categorías para mujer
            'tops' => [
                'Camiseta' => 'Camisetas',
                'Camisa' => 'Camisas',
                'Blusa' => 'Tops',  // No hay categoría específica para blusas
                'Polo' => 'Tops',  // No hay categoría específica para polos
                'Jersey' => 'Jerséis',
                'Sudadera' => 'Sudaderas'
            ],
            'outerwear' => [
                'Chaqueta' => 'Chaquetas',
                'Cazadora' => 'Chaquetas',  // No existe categoría específica para cazadoras
                'Abrigo' => 'Abrigos',
                'Chaleco' => 'Chalecos',
                'Impermeable' => 'Parkas',  // Los impermeables van en Parkas
                'Anorak' => 'Parkas',  // Los anoraks van en Parkas
                'Americana' => 'Americanas'
            ],
            'bottoms' => [
                'Pantalón' => 'Pantalones',
                'Vaquero' => 'Jeans',
                'Bermuda' => 'Shorts',  // Las bermudas van en Shorts
                'Short' => 'Shorts',
                'Falda' => 'Faldas',
                'Legging' => 'Leggings'
            ],
            'dresses' => [
                'Vestido' => 'Vestidos',
                'Mono' => 'Monos',
                'Peto' => 'Monos'  // Los petos van en Monos
            ],
            'sportswear' => [
                'Chándal' => 'Sudaderas'  // No hay categoría específica para chándal, usamos Sudaderas
            ],
            'sleepwear' => [
                'Pijama' => 'Pijamas',
                'Bata' => 'Pijamas',  // No hay categoría específica para batas
                'Albornoz' => 'Pijamas'  // No hay categoría específica para albornoces
            ],
            'underwear' => [
                'Ropa interior' => 'Bragas',  // Elegimos una subcategoría genérica
                'Sujetador' => 'Sujetadores',
                'Braga' => 'Bragas',
                'Calzoncillo' => 'Calzoncillos',  // Esto iría en hombre
                'Bóxer' => 'Calzoncillos',  // Esto iría en hombre
                'Body' => 'Bodys'
            ],
            'legwear' => [
                'Medias' => 'Leggings',  // No hay categoría específica para medias
                'Pantis' => 'Leggings',  // No hay categoría específica para pantis
                'Leotardos' => 'Leggings',  // No hay categoría específica para leotardos
                'Calcetines' => 'Leggings'  // No hay categoría específica para calcetines
            ],
            'accessories' => [
                'Guantes' => 'Guantes',
                'Bufanda' => 'Bufandas',
                'Gorro' => 'Gorros',
                'Sombrero' => 'Gorros',  // Los sombreros van en Gorros
                'Pañuelo' => 'Bufandas',  // Los pañuelos van en Bufandas
                'Corbata' => 'Cinturones',  // No hay categoría específica para corbatas
                'Pajarita' => 'Cinturones',  // No hay categoría específica para pajaritas
                'Cinturón' => 'Cinturones',
                'Joyería' => 'Joyería',
                'Bolso' => 'Bolsos',
                'Mochila' => 'Mochilas',
                'Gafas de sol' => 'Gafas de sol'
            ]
        ];
        
        // Determinar si es ropa de mujer u hombre basado en el tipo de producto
        $gender = $this->determineGender($productType);
        
        // Obtener la categoría principal según el género
        $mainCategory = $gender === 'mujer' ? 'Mujer - ' : 'Hombre - ';
        
        // Determinar el tipo de categoría principal basado en el grupo de categoría
        $categoryType = '';
        if (in_array($categoryGroup, ['tops', 'outerwear', 'bottoms', 'dresses', 'sportswear'])) {
            $categoryType = 'Ropa';
        } elseif (in_array($categoryGroup, ['sleepwear', 'underwear'])) {
            $categoryType = 'Ropa interior';
        } elseif ($categoryGroup === 'accessories') {
            $categoryType = 'Accesorios';
        } else {
            $categoryType = 'Ropa'; // Por defecto
        }
        
        $mainCategoryName = $mainCategory . $categoryType;
        
        // Determinar el nombre de la subcategoría
        $subCategoryName = null;
        if (isset($categoryMapping[$categoryGroup][$productType])) {
            $subCategoryName = $categoryMapping[$categoryGroup][$productType];
        }
        
        if (!$subCategoryName) {
            $this->logger->warning(
                sprintf('[FashionProductManager] No se encontró mapeo de categoría para %s (%s)', $productType, $categoryGroup)
            );
            return null;
        }
        
        try {
            // Primero buscamos la categoría principal
            $mainCategoryCollection = $this->categoryCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('name', $mainCategoryName)
                ->setPageSize(1);
            
            if ($mainCategoryCollection->getSize() === 0) {
                $this->logger->warning(
                    sprintf('[FashionProductManager] No se encontró la categoría principal %s', $mainCategoryName)
                );
                return null;
            }
            
            $mainCategoryId = $mainCategoryCollection->getFirstItem()->getId();
            
            // Ahora buscamos la subcategoría
            $subCategoryCollection = $this->categoryCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('name', $subCategoryName)
                ->addAttributeToFilter('parent_id', $mainCategoryId)
                ->setPageSize(1);
            
            if ($subCategoryCollection->getSize() > 0) {
                return (int)$subCategoryCollection->getFirstItem()->getId();
            }
            
            // Si no encontramos la subcategoría exacta, intentamos con una búsqueda aproximada
            $subCategoryCollection = $this->categoryCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('name', ['like' => '%' . $subCategoryName . '%'])
                ->addAttributeToFilter('parent_id', $mainCategoryId)
                ->setPageSize(1);
            
            if ($subCategoryCollection->getSize() > 0) {
                return (int)$subCategoryCollection->getFirstItem()->getId();
            }
            
            // Si no encontramos ninguna subcategoría, devolvemos la categoría principal
            $this->logger->info(
                sprintf('[FashionProductManager] No se encontró la subcategoría %s, usando categoría principal %s', 
                    $subCategoryName, $mainCategoryName)
            );
            return $mainCategoryId;
            
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('[FashionProductManager] Error al buscar categoría: %s', $e->getMessage())
            );
            return null;
        }
    }

    /**
     * Determina si un producto es para hombre o mujer basado en el tipo
     * 
     * @param string $productType
     * @return string 'mujer' o 'hombre'
     */
    private function determineGender(string $productType): string
    {
        // Productos específicamente masculinos
        $menProducts = [
            'Calzoncillo', 'Bóxer', 'Corbata', 'Pajarita', 'Cazadora', 'Americana',
        ];
        
        return in_array($productType, $menProducts) ? 'hombre' : 'mujer';
    }

    /**
     * Genera una URL amigable para SEO
     * 
     * @param string $name Nombre del producto
     * @param string $uniqueId ID único para evitar duplicados
     * @return string
     */
    private function generateUrlKey(string $name, string $uniqueId): string 
    {
        $urlKey = strtolower(trim($name));
        $urlKey = preg_replace('/[^a-z0-9]+/', '-', $urlKey);
        $urlKey = preg_replace('/-+/', '-', $urlKey);
        $urlKey = trim($urlKey, '-');
        
        return $urlKey . '-' . $uniqueId;
    }

    /**
     * Obtiene los datos de atributos configurables
     *
     * @return array
     * @throws NoSuchEntityException
     */
    private function getConfigurableAttributesData(): array
    {
        $attributesData = [];
        
        foreach ($this->configurableAttributes as $attributeCode) {
            /** @var Attribute $attribute */
            $attribute = $this->attributeRepository->get(self::ATTRIBUTE_TYPE, $attributeCode);
            
            $attributesData[] = [
                'attribute_id' => $attribute->getAttributeId(),
                'code' => $attribute->getAttributeCode(),
                'label' => $attribute->getStoreLabel(),
                'position' => '0',
                'values' => [],
            ];
        }
        
        return $attributesData;
    }

    /**
     * Obtiene las opciones disponibles para los atributos configurables
     *
     * @return array
     * @throws LocalizedException
     */
    private function getAttributeOptions(): array
    {
        $result = [];
        
        foreach ($this->configurableAttributes as $attributeCode) {
            $options = $this->attributeOptionManagement->getItems(4, $attributeCode);
            
            // Seleccionar aleatoriamente 3 opciones o menos si no hay suficientes
            $selectedOptions = [];
            $maxOptions = min(count($options), 4);
            
            if ($maxOptions === 0) {
                throw new LocalizedException(__('El atributo "%1" no tiene opciones. Añade opciones antes de ejecutar este comando.', $attributeCode));
            }
            
            $randomKeys = array_rand($options, $maxOptions);
            if (!is_array($randomKeys)) {
                $randomKeys = [$randomKeys];
            }
            
            foreach ($randomKeys as $key) {
                $option = $options[$key];
                $selectedOptions[] = [
                    'value_index' => $option->getValue(),
                    'label' => $option->getLabel(),
                ];
            }
            
            $result[$attributeCode] = $selectedOptions;
        }
        
        return $result;
    }

    /**
     * Crea los productos simples (variaciones)
     *
     * @param array $attributeOptions
     * @param string $parentSku
     * @param int $attributeSetId
     * @param array $fashionProductData Datos del producto de moda
     * @return array
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    private function createVariations(array $attributeOptions, string $parentSku, int $attributeSetId, array $fashionProductData): array
    {
        $simpleProducts = [];
        $variations = $this->generateVariationCombinations($attributeOptions);
        
        // Verificar que tengamos variaciones
        if (empty($variations)) {
            $this->logger->warning('[FashionProductManager] No se generaron variaciones para el producto ' . $parentSku);
            return $simpleProducts;
        }
        
        $requiredAttributes = array_keys($attributeOptions);
        $baseName = $fashionProductData['name'];
        $basePrice = mt_rand(20, 80); // Precio base del producto
        
        foreach ($variations as $index => $variation) {
            try {
                // Verificar que la variación contenga todos los atributos requeridos
                $missingAttributes = array_diff($requiredAttributes, array_keys($variation));
                if (!empty($missingAttributes)) {
                    $this->logger->warning(
                        sprintf(
                            '[FashionProductManager] Variación %d para %s no tiene todos los atributos requeridos. Faltan: %s', 
                            $index + 1, 
                            $parentSku, 
                            implode(', ', $missingAttributes)
                        )
                    );
                    continue; // Saltar esta variación incompleta
                }
                
                $attributes = [];
                $nameSuffix = [];
                $priceModifiers = []; // Para ajustar el precio según atributos
                
                foreach ($variation as $attributeCode => $optionData) {
                    // Verificar que los datos del atributo sean correctos
                    if (!isset($optionData['value_index']) || !isset($optionData['label'])) {
                        $this->logger->warning(
                            sprintf(
                                '[FashionProductManager] Datos de atributo incompletos para %s en variación %d', 
                                $attributeCode, 
                                $index + 1
                            )
                        );
                        continue 2; // Saltar esta variación con datos de atributo incompletos
                    }
                    
                    $attributes[$attributeCode] = $optionData['value_index'];
                    $nameSuffix[] = $optionData['label'];
                    
                    // Añadir modificadores de precio basados en atributos
                    // Por ejemplo, tallas más grandes pueden costar un poco más
                    if ($attributeCode === 'size') {
                        $sizeLabel = strtoupper($optionData['label']);
                        if (in_array($sizeLabel, ['XL', 'XXL', '2XL', '3XL'])) {
                            $priceModifiers[] = 5; // 5€ más por tallas grandes
                        }
                    }
                }
                
                // Si no tenemos suficientes atributos para crear un nombre adecuado, saltar
                if (count($nameSuffix) !== count($requiredAttributes)) {
                    $this->logger->warning(
                        sprintf(
                            '[FashionProductManager] Cantidad insuficiente de atributos para variación %d de %s', 
                            $index + 1, 
                            $parentSku
                        )
                    );
                    continue;
                }
                
                /** @var Product $simpleProduct */
                $simpleProduct = $this->productFactory->create();
                
                $suffix = implode(' - ', $nameSuffix);
                $variantName = $baseName . ' - ' . $suffix;
                $sku = $parentSku . '-' . ($index + 1);
                
                // Calcular precio final con modificadores
                $finalPrice = $basePrice + array_sum($priceModifiers);
                
                $simpleProduct->setTypeId(ProductType::TYPE_SIMPLE)
                    ->setAttributeSetId($attributeSetId)
                    ->setName($variantName)
                    ->setSku($sku)
                    ->setUrlKey($this->generateUrlKey($variantName, substr(uniqid(), -4)))
                    ->setStatus(Status::STATUS_ENABLED)
                    ->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE)
                    ->setPrice($finalPrice)
                    ->setShortDescription($fashionProductData['short_description'])
                    ->setDescription($fashionProductData['description']);
                    
                // Añadir atributos personalizados si existen
                $simpleProduct->setData('material', $fashionProductData['material']);
                $simpleProduct->setData('estilo', $fashionProductData['style']);
                $simpleProduct->setData('temporada', $fashionProductData['season']);
                    
                // Establecer valores de atributos configurables y verificar asignación
                foreach ($attributes as $code => $value) {
                    $simpleProduct->setData($code, $value);
                    
                    // Verificar que el atributo se haya asignado correctamente
                    if ($simpleProduct->getData($code) != $value) {
                        $this->logger->warning(
                            sprintf(
                                '[FashionProductManager] No se pudo asignar atributo %s con valor %s a variación %s', 
                                $code, 
                                $value, 
                                $sku
                            )
                        );
                    }
                }
                
                // Verificación final de que todos los atributos requeridos estén presentes
                $allAttributesSet = true;
                foreach ($requiredAttributes as $attrCode) {
                    if (!$simpleProduct->getData($attrCode)) {
                        $allAttributesSet = false;
                        $this->logger->warning(
                            sprintf(
                                '[FashionProductManager] Atributo %s no está asignado en producto %s antes de guardar', 
                                $attrCode, 
                                $sku
                            )
                        );
                    }
                }
                
                if (!$allAttributesSet) {
                    $this->logger->error(
                        sprintf('[FashionProductManager] No se guardará variación %s por falta de atributos requeridos', $sku)
                    );
                    continue;
                }
                
                $simpleProduct = $this->productRepository->save($simpleProduct);
                $simpleProducts[] = $simpleProduct;

                // --- Integración MSI ---
                // Variar el stock según la combinación para que sea más realista
                // Por ejemplo, tallas extremas pueden tener menos stock
                $qty = 100;
                
                // Ajustar cantidad basada en atributos
                foreach ($attributes as $code => $value) {
                    if ($code === 'size') {
                        $sizeLabel = null;
                        // Obtener la etiqueta de la talla
                        foreach ($attributeOptions['size'] as $sizeOption) {
                            if ($sizeOption['value_index'] == $value) {
                                $sizeLabel = strtoupper($sizeOption['label']);
                                break;
                            }
                        }
                        
                        // Variar stock según talla
                        if ($sizeLabel) {
                            if (in_array($sizeLabel, ['XXS', 'XS', '3XL', 'XXL'])) {
                                $qty = mt_rand(10, 30); // Menos stock para tallas extremas
                            } elseif (in_array($sizeLabel, ['S', 'XL'])) {
                                $qty = mt_rand(30, 70); // Stock medio para tallas menos comunes
                            } else {
                                $qty = mt_rand(70, 150); // Mayor stock para tallas comunes (M, L)
                            }
                        }
                    }
                }
                
                $sourceItem = $this->sourceItemFactory->create();
                $sourceItem->setSourceCode('default');
                $sourceItem->setSku($simpleProduct->getSku());
                $sourceItem->setQuantity($qty);
                $sourceItem->setStatus($qty > 0
                    ? \Magento\InventoryApi\Api\Data\SourceItemInterface::STATUS_IN_STOCK
                    : \Magento\InventoryApi\Api\Data\SourceItemInterface::STATUS_OUT_OF_STOCK);
                
                $this->sourceItemsSave->execute([$sourceItem]);
                
                $this->logger->info(
                    sprintf(
                        '[FashionProductManager] Variación %s (%s) creada exitosamente con atributos: %s, stock: %d', 
                        $variantName,
                        $sku, 
                        implode(', ', array_map(function($k, $v) use ($attributeOptions) { 
                            $label = 'desconocido';
                            foreach ($attributeOptions[$k] as $option) {
                                if ($option['value_index'] == $v) {
                                    $label = $option['label'];
                                    break;
                                }
                            }
                            return "$k: $label"; 
                        }, array_keys($attributes), array_values($attributes))),
                        $qty
                    )
                );
                
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf(
                        '[FashionProductManager] Error al crear variación %d para %s: %s', 
                        $index + 1, 
                        $parentSku, 
                        $e->getMessage()
                    )
                );
                // Continuar con la siguiente variación en lugar de interrumpir todo el proceso
            }
        }
        
        // Registro final
        $this->logger->info(
            sprintf(
                '[FashionProductManager] Creadas %d variaciones para producto %s de %d combinaciones posibles', 
                count($simpleProducts), 
                $parentSku, 
                count($variations)
            )
        );
        
        return $simpleProducts;
    }

    /**
     * Genera todas las combinaciones posibles de atributos para variaciones
     *
     * @param array $attributeOptions
     * @return array
     */
    private function generateVariationCombinations(array $attributeOptions): array
    {
        $result = [[]];
        
        foreach ($attributeOptions as $attributeCode => $options) {
            $tmp = [];
            
            foreach ($result as $current) {
                foreach ($options as $option) {
                    $current[$attributeCode] = $option;
                    $tmp[] = $current;
                }
            }
            
            $result = $tmp;
        }
        
        return $result;
    }

    /**
     * Asocia los productos simples al producto configurable
     *
     * @param ProductInterface $configurableProduct
     * @param array $simpleProducts
     * @param array $attributesData
     * @return void
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    private function associateProducts(
        ProductInterface $configurableProduct,
        array $simpleProducts,
        array $attributesData
    ): void {
        // Preparar datos de atributos configurables
        $configurableAttributesData = [];
        $configurableProductsData = [];
        
        foreach ($attributesData as &$attribute) {
            $attributeValues = [];
            $attributeId = $attribute['attribute_id'];
            
            foreach ($simpleProducts as $product) {
                $attributeValue = $product->getData($attribute['code']);
                if (!in_array(['value_index' => $attributeValue], $attributeValues, true)) {
                    $attributeValues[] = ['value_index' => $attributeValue];
                }
                
                // Agregar este producto a la lista de productos configurables
                $configurableProductsData[] = [
                    'id' => $product->getId(),
                    'position' => 0,
                    'sku' => $product->getSku(),
                ];
            }
            
            $attribute['values'] = $attributeValues;
            $configurableAttributesData[] = [
                'attribute_id' => $attributeId,
                'code' => $attribute['code'],
                'label' => $attribute['label'],
                'position' => $attribute['position'],
                'values' => $attributeValues,
            ];
        }
        
        // Crear opciones configurables
        $options = $this->optionFactory->create($configurableAttributesData);
        
        /** @var Product $configurableProduct */
        $configurableProduct->getExtensionAttributes()
            ->setConfigurableProductOptions($options)
            ->setConfigurableProductLinks(array_map(
                fn($product) => $product->getId(),
                $simpleProducts
            ));
        
        $this->productRepository->save($configurableProduct);
    }
    // Fin Funciones para los configurables

}        