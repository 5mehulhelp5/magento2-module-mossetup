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
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Downloadable\Api\Data\LinkInterfaceFactory as DownloadableLinkFactory;
use Magento\Downloadable\Api\LinkRepositoryInterface;
use Magento\Downloadable\Model\Product\Type as DownloadableType;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as OptionsFactory;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Bundle\Api\Data\OptionInterfaceFactory;
use Magento\Bundle\Api\Data\LinkInterfaceFactory;
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

class ProductManager
{
    private const PRODUCT_COUNT_PER_TYPE = 10;
    private const ATTRIBUTE_TYPE = 4;
   
    public function __construct(
        private readonly ProductInterfaceFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ProductLinkInterfaceFactory $productLinkFactory,
        private readonly ProductLinkRepositoryInterface $productLinkRepository,
        private readonly DownloadableLinkFactory $downloadableLinkFactory,
        private readonly LinkRepositoryInterface $linkRepository,
        private readonly ExtensionAttributesFactory $extensionAttributesFactory,
        private readonly AttributeRepositoryInterface $attributeRepository,
        private readonly OptionsFactory $optionsFactory,
        private readonly \Magento\Downloadable\Api\Data\File\ContentInterfaceFactory $downloadableContentFactory,
        private readonly \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        private readonly OptionInterfaceFactory $optionInterfaceFactory,
        private readonly LinkInterfaceFactory $linkInterfaceFactory,
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

    public function createProducts(): void
    {
        $this->secureContextExecutor->execute(function (): void {
            $this->logger->info('[ProductManager] Entro en helper', ['mensaje' => 'Empezamos...']);

            $this->createSimpleProducts();
            $this->createVirtualProducts();
            $this->createDownloadableProducts();
            $this->createGroupedProducts();
            $this->createBundleProducts();
        });
    }

    private function getRandomCategoryId(): ?int
    {
        $collection = $this->categoryCollectionFactory->create()
            ->addAttributeToFilter('level', ['gt' => 1])
            ->setPageSize(100);
        $items = $collection->getItems();

        if (empty($items)) {
            return null;
        }

        return (int) array_rand($items);
    }

    private function buildSimpleProduct(string $sku, string $name, string $typeId, float $price, array $data = [], bool $useMasterStock = false): ProductInterface
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->productFactory->create();
        $product->setSku($sku)
                ->setName($name)
                ->setAttributeSetId($this->getDefaultSet())
                ->setStatus(Status::STATUS_ENABLED)
                ->setVisibility(Visibility::VISIBILITY_BOTH)
                ->setTypeId($typeId)
                ->setPrice($price)
                ->setWeight(1.0)
                ->setStockData($useMasterStock ? $this->getMasterStockData() : $this->getDefaultStockData())
                ->setDescription("Descripción larga para {$name}.")
                ->setShortDescription("Descripción corta para {$name}.");

        foreach ($data as $key => $value) {
            $product->setData($key, $value);
        }

        if (($categoryId = $this->getRandomCategoryId()) !== null) {
            $product->setCategoryIds([$categoryId]);
        }

        return $product;
    }

    private function getDefaultStockData(int $qty = 100): array
    {
        return [
            'use_config_manage_stock' => 1,
            'qty' => $qty,
            'is_qty_decimal' => 0,
            'is_in_stock' => 1
        ];
    }

    private function getMasterStockData(): array
    {
        return [
            'use_config_manage_stock' => 1,
            'is_in_stock' => 1,
            'manage_stock' => 0
        ];
    }

    public function createDownloadableProducts(): void
    {
        for ($i = 1; $i <= self::PRODUCT_COUNT_PER_TYPE; $i++) {
            try {
                $sku = "downloadable-{$i}";
                $name = "Producto Descargable {$i}";
                $product = $this->buildSimpleProduct($sku, $name, DownloadableType::TYPE_DOWNLOADABLE, 15.00 * $i);
                $product->setLinksPurchasedSeparately(1)->setLinksExist(1);

                $savedProduct = $this->productRepository->save($product);

                $fileName = "manual_{$i}.txt";
                $fileContent = "Este es el contenido del manual para el producto {$i}";
                $contentInterface = $this->downloadableContentFactory->create();
                $contentInterface->setFileData(base64_encode($fileContent));
                $contentInterface->setName($fileName);

                $link = $this->downloadableLinkFactory->create();
                $link->setTitle("Manual del producto {$i}")
                     ->setIsShareable(1)
                     ->setLinkType('file')
                     ->setPrice(0.00)
                     ->setSortOrder(1)
                     ->setNumberOfDownloads(0)
                     ->setLinkFileContent($contentInterface);

                $this->linkRepository->save($savedProduct->getSku(), $link);

            } catch (\Exception $e) {
                $this->logger->error("Error al crear producto descargable {$i}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    public function createSimpleProducts(): void
    {
        for ($i = 1; $i <= self::PRODUCT_COUNT_PER_TYPE; $i++) {
            try {
                $sku = "simple-{$i}";
                $name = "Producto Simple {$i}";
                $product = $this->buildSimpleProduct($sku, $name, Type::TYPE_SIMPLE, 10.00 * $i);
                $this->productRepository->save($product);

                $this->logger->info('[ProductManager] Creado', [
                    'nombre' => $name
                ]);

            } catch (\Exception $e) {
                $this->logger->error("[ProductManager] Error al crear producto simple {$i}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    public function createVirtualProducts(): void
    {
        for ($i = 1; $i <= self::PRODUCT_COUNT_PER_TYPE; $i++) {
            try {
                $sku = "virtual-{$i}";
                $name = "Producto Virtual {$i}";
                $product = $this->buildSimpleProduct($sku, $name, Type::TYPE_VIRTUAL, 20.00 * $i);
                $this->productRepository->save($product);
            } catch (\Exception $e) {
                $this->logger->error("[ProductManager] Error al crear producto virtual {$i}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    public function createGroupedProducts(): void
    {
        $simpleProducts = $this->productCollectionFactory->create()
            ->addAttributeToFilter('type_id', Type::TYPE_SIMPLE)
            ->setPageSize(10)
            ->getItems();

        if (empty($simpleProducts)) {
            $this->logger->warning("[ProductManager] No se encontraron productos simples para productos agrupados.");
            return;
        }

        for ($i = 1; $i <= self::PRODUCT_COUNT_PER_TYPE; $i++) {
            try {
                $sku = "grouped-{$i}";
                $name = "Producto Agrupado {$i}";
                $product = $this->buildSimpleProduct($sku, $name, Grouped::TYPE_CODE, 0.0, [], true);

                $savedProduct = $this->productRepository->save($product);
                $position = 0;
                foreach (array_slice($simpleProducts, 0, 3) as $simpleProduct) {
                    $link = $this->productLinkFactory->create();
                    $link->setSku($savedProduct->getSku())
                         ->setLinkType('associated')
                         ->setLinkedProductSku($simpleProduct->getSku())
                         ->setLinkedProductType(Type::TYPE_SIMPLE)
                         ->setPosition($position++)
                         ->setQty(1);
                    $this->productLinkRepository->save($link);
                }
            } catch (\Exception $e) {
                $this->logger->error("[ProductManager] Error al crear producto agrupado {$i}", ['error' => $e->getMessage()]);
            }
        }
    }

    public function createBundleProducts(): void
    {
        // Obtener productos simples existentes del catálogo
        $simpleProducts = $this->productCollectionFactory->create()
            ->addAttributeToFilter('type_id', Type::TYPE_SIMPLE)
            ->addAttributeToFilter('status', Status::STATUS_ENABLED) // Solo productos activos
            ->addFieldToFilter('visibility', ['neq' => Visibility::VISIBILITY_NOT_VISIBLE]) // Con visibilidad adecuada
            ->addAttributeToSelect('*') // Seleccionamos todos los atributos
            ->setPageSize(10)
            ->setCurPage(1)
            ->getItems();

        if (empty($simpleProducts)) {
            $this->logger->warning("[ProductManager] No se encontraron productos simples existentes para crear productos bundle.");
            return;
        }

        // Convertir a array para facilitar su manejo
        $simpleProductArray = [];
        foreach ($simpleProducts as $product) {
            $simpleProductArray[] = $product;
        }

        // Asegurarnos de que tenemos suficientes productos
        if (count($simpleProductArray) < 6) {
            $this->logger->warning("[ProductManager] No hay suficientes productos simples para crear bundles completos. Se encontraron: " . count($simpleProductArray));
        }

        for ($i = 1; $i <= self::PRODUCT_COUNT_PER_TYPE; $i++) {
            try {
                $sku = "xbundle-{$i}";
                $name = "Producto Bundle {$i}";
                
                // Verificar si el producto bundle ya existe
                try {
                    $this->logger->info("[ProductManager] El producto bundle {$sku} ya existe, se omitirá su creación");
                    continue;
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    // El producto no existe, continuamos con la creación
                }
                
                // Crear el producto bundle
                /** @var \Magento\Catalog\Model\Product $product */
                $product = $this->productFactory->create();
                $product->setSku($sku);
                $product->setName($name);
                $product->setTypeId(BundleType::TYPE_CODE);
                
                $product->setAttributeSetI($this->getDefaultSet());
                $product->setStatus(Status::STATUS_ENABLED);
                $product->setVisibility(Visibility::VISIBILITY_BOTH);
                
                // Configuraciones específicas del bundle
                $product->setPrice(0.00);
                $product->setPriceType(0); // 0 = dinámico, 1 = fijo
                $product->setPriceView(0); // 0 = precio, 1 = rango de precio
                $product->setSkuType(0);   // 0 = dinámico, 1 = fijo
                $product->setWeightType(0); // 0 = dinámico, 1 = fijo
                $product->setShipmentType(0); // 0 = juntos, 1 = separados    
                $product->setStockData($this->getMasterStockData());
                
                // Guardar el producto base para después añadirle las opciones
                $savedProduct = $this->productRepository->save($product);
                
                // Determinar cuántos productos usar para cada opción basado en disponibilidad
                $maxProductsForOption1 = min(3, count($simpleProductArray));
                $maxProductsForOption2 = min(3, count($simpleProductArray) - $maxProductsForOption1);
                
                // Crear opciones del bundle con los productos existentes
                $bundleOptions = [];
                
                if ($maxProductsForOption1 > 0) {
                    $bundleOptions[] = $this->createBundleOption(
                        'Opción Bundle 1',
                        array_slice($simpleProductArray, 0, $maxProductsForOption1),
                        1, // required
                        'select' // type
                    );
                }
                
                if ($maxProductsForOption2 > 0) {
                    $bundleOptions[] = $this->createBundleOption(
                        'Opción Bundle 2',
                        array_slice($simpleProductArray, $maxProductsForOption1, $maxProductsForOption2),
                        0, // no required
                        'checkbox' // type
                    );
                }
                
                if (!empty($bundleOptions)) {
                    $extension = $savedProduct->getExtensionAttributes();
                    if (!$extension) {
                        $extension = $this->extensionAttributesFactory->create(ProductInterface::class);
                    }
                    
                    $extension->setBundleProductOptions($bundleOptions);
                    $savedProduct->setExtensionAttributes($extension);
                    $this->productRepository->save($savedProduct);
                    
                    $this->logger->info("Producto bundle {$sku} creado exitosamente con " . count($bundleOptions) . " opciones");
                } else {
                    $this->logger->warning("No se pudieron añadir opciones al producto bundle {$sku}");
                }
            } catch (\Exception $e) {
                $this->logger->error("Error al crear producto bundle {$i}", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            }
        }
    }

    /**
     * Crear una opción de bundle
     *
     * @param string $title
     * @param array $products
     * @param int $required
     * @param string $type
     * @return \Magento\Bundle\Api\Data\OptionInterface
     */
    private function createBundleOption(string $title, array $products, int $required = 1, string $type = 'select'): \Magento\Bundle\Api\Data\OptionInterface
    {
        /** @var \Magento\Bundle\Api\Data\OptionInterface $option */
        $option = $this->optionInterfaceFactory->create();
        $option->setTitle($title);
        $option->setRequired($required);
        $option->setType($type);
        $option->setPosition(0);
        $option->setSku('');
        
        $optionSelections = [];
        $position = 0;
        
        foreach ($products as $product) {
            /** @var \Magento\Bundle\Api\Data\LinkInterface $link */
            $link = $this->linkInterfaceFactory->create();
            $link->setSku($product->getSku());
            $link->setQty(1);
            $link->setPrice(0); // Precio 0 implica usar el precio del producto simple
            $link->setPriceType(0); // 0 = fijo, 1 = porcentual
            $link->setPosition($position++);
            $link->setIsDefault(0); // 0 = no, 1 = sí
            $link->setCanChangeQuantity(1); // 0 = no, 1 = sí
            
            $optionSelections[] = $link;
        }
        
        $option->setProductLinks($optionSelections);
        
        return $option;
    }

    // Funciones para los configurables
    public function createConfigurableProducts(int $count) {

        $this->secureContextExecutor->execute(function () use ($count): void {
            
            $this->logger->info('[ProductManager] Entro en helper', ['mensaje' => 'Empezamos  crear configurables...']);

            // Encontrar un conjunto de atributos válido
            $attributeSetId = $this->getDefaultSet();
            
            if (!$attributeSetId) {
                throw new LocalizedException(__('[ProductManager] No se encontró el conjunto de atributos válido'));
            }
            
            $this->logger->info(sprintf('[ProductManager] Usando conjunto de atributos ID: %d', $attributeSetId));
            
            // Obtener opciones para cada atributo (se hace una sola vez para reutilizar)
            $attributeOptions = $this->getAttributeOptions();
            $attributesData = $this->getConfigurableAttributesData();
            
            // Bucle para crear el número solicitado de productos configurables
            for ($i = 1; $i <= $count; $i++) {
                
                $this->logger->info(sprintf('[ProductManager] Creando producto configurable %d de %d.', $i, $count));
                
                // Crear el producto configurable
                $configurableProduct = $this->createConfigurableProduct($i, $attributeSetId);
                
                // Crear productos simples (variaciones)
                $simpleProducts = $this->createVariations($attributeOptions, $configurableProduct->getSku(), $attributeSetId);
                
                // Asociar productos simples al configurable
                $this->associateProducts($configurableProduct, $simpleProducts, $attributesData);
                
                $this->logger->info(sprintf(
                    '[ProductManager] Producto configurable "%s" creado con %d variaciones.',
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
     * @return ProductInterface
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    private function createConfigurableProduct(int $index, int $attributeSetId): ProductInterface
    {
        /** @var Product $product */
        $product = $this->productFactory->create();
        
        $uniqueId = uniqid();
        $suffix = $index . '-' . $uniqueId;
        
        $product->setTypeId(Configurable::TYPE_CODE)
            ->setAttributeSetId($attributeSetId)
            ->setName('Producto Configurable ' . $suffix)
            ->setSku('CONF-' . $suffix)
            ->setUrlKey('producto-configurable-' . $suffix)
            ->setStatus(Status::STATUS_ENABLED)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStockData($this->getMasterStockData())
            ->setPrice(0.0);

        // Para que se puedan asociar las combinaciones por los atributos configurables
        foreach ($this->configurableAttributes as $attributeCode) {
            $product->setData($attributeCode, null);
        }

        if (($categoryId = $this->getRandomCategoryId()) !== null) {
            $product->setCategoryIds([$categoryId]);
        }

        return $this->productRepository->save($product);
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
            $maxOptions = min(count($options), 3);
            
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
     * @return array
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    private function createVariations(array $attributeOptions, string $parentSku, int $attributeSetId): array
    {
        $simpleProducts = [];
        $variations = $this->generateVariationCombinations($attributeOptions);
        
        // Verificar que tengamos variaciones
        if (empty($variations)) {
            $this->logger->warning('[ProductManager] No se generaron variaciones para el producto ' . $parentSku);
            return $simpleProducts;
        }
        
        $requiredAttributes = array_keys($attributeOptions);
        
        foreach ($variations as $index => $variation) {
            try {
                // Verificar que la variación contenga todos los atributos requeridos
                $missingAttributes = array_diff($requiredAttributes, array_keys($variation));
                if (!empty($missingAttributes)) {
                    $this->logger->warning(
                        sprintf(
                            '[ProductManager] Variación %d para %s no tiene todos los atributos requeridos. Faltan: %s', 
                            $index + 1, 
                            $parentSku, 
                            implode(', ', $missingAttributes)
                        )
                    );
                    continue; // Saltar esta variación incompleta
                }
                
                $attributes = [];
                $nameSuffix = [];
                
                foreach ($variation as $attributeCode => $optionData) {
                    // Verificar que los datos del atributo sean correctos
                    if (!isset($optionData['value_index']) || !isset($optionData['label'])) {
                        $this->logger->warning(
                            sprintf(
                                '[ProductManager] Datos de atributo incompletos para %s en variación %d', 
                                $attributeCode, 
                                $index + 1
                            )
                        );
                        continue 2; // Saltar esta variación con datos de atributo incompletos
                    }
                    
                    $attributes[$attributeCode] = $optionData['value_index'];
                    $nameSuffix[] = $optionData['label'];
                }
                
                // Si no tenemos suficientes atributos para crear un nombre adecuado, saltar
                if (count($nameSuffix) !== count($requiredAttributes)) {
                    $this->logger->warning(
                        sprintf(
                            '[ProductManager] Cantidad insuficiente de atributos para variación %d de %s', 
                            $index + 1, 
                            $parentSku
                        )
                    );
                    continue;
                }
                
                /** @var Product $simpleProduct */
                $simpleProduct = $this->productFactory->create();
                
                $suffix = implode('-', $nameSuffix);
                $sku = $parentSku . '-' . ($index + 1);
                
                $simpleProduct->setTypeId(ProductType::TYPE_SIMPLE)
                    ->setAttributeSetId($attributeSetId)
                    ->setName('Variación ' . $suffix)
                    ->setSku($sku)
                    ->setUrlKey('variacion-simple-' . strtolower(str_replace(' ', '-', $suffix)) . '-' . uniqid())
                    ->setStatus(Status::STATUS_ENABLED)
                    ->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE)
                    ->setPrice(mt_rand(10, 100));
                    
                // Establecer valores de atributos configurables y verificar asignación
                foreach ($attributes as $code => $value) {
                    $simpleProduct->setData($code, $value);
                    
                    // Verificar que el atributo se haya asignado correctamente
                    if ($simpleProduct->getData($code) != $value) {
                        $this->logger->warning(
                            sprintf(
                                '[ProductManager] No se pudo asignar atributo %s con valor %s a variación %s', 
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
                                '[ProductManager] Atributo %s no está asignado en producto %s antes de guardar', 
                                $attrCode, 
                                $sku
                            )
                        );
                    }
                }
                
                if (!$allAttributesSet) {
                    $this->logger->error(
                        sprintf('[ProductManager] No se guardará variación %s por falta de atributos requeridos', $sku)
                    );
                    continue;
                }
                
                $simpleProduct = $this->productRepository->save($simpleProduct);
                $simpleProducts[] = $simpleProduct;

                // --- Integración MSI ---
                $qty = 100;
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
                        '[ProductManager] Variación %s creada exitosamente con atributos: %s', 
                        $sku, 
                        implode(', ', array_map(function($k, $v) { 
                            return "$k: $v"; 
                        }, array_keys($attributes), array_values($attributes)))
                    )
                );
                
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf(
                        '[ProductManager] Error al crear variación %d para %s: %s', 
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
                '[ProductManager] Creadas %d variaciones para producto %s de %d combinaciones posibles', 
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

    public function deleteSingleProduct(int $productId): void
    {
        $this->secureContextExecutor->execute(function () use ($productId): void {
            try {
                $product = $this->productRepository->getById($productId);
                $this->productResource->delete($product);
                $this->logger->info('[ProductManager] Producto ID ' . $productId . ' eliminado correctamente.');
            } catch (\Exception $e) {
                $this->logger->error('[ProductManager] Error al eliminar producto ID ' . $productId . ': ' . $e->getMessage());
            }
        });
    }

    public function deleteAllProducts(int $batchSize = 100): void
    {
        $deletedCount = 0;

        $this->secureContextExecutor->execute(function () use ($batchSize, &$deletedCount): void {
            do {
                // Siempre cargar la primera página para que, al eliminar, se reordene la colección.
                $collection = $this->productCollectionFactory->create();
                $collection->addAttributeToSelect('sku')
                    ->setPageSize($batchSize)
                    ->setCurPage(1);
                $collection->load();

                $currentBatchSize = $collection->getSize();
                if ($currentBatchSize === 0) {
                    break;
                }

                foreach ($collection as $product) {
                    try {
                        $productId = (int)$product->getId();
                        $sku = $product->getSku();
                        $this->productResource->delete($product);
                        $deletedCount++;
                        $this->logger->info('[ProductManager]Producto eliminado: ID ' . $productId . ' (SKU: ' . $sku . ')');
                    } catch (\Exception $e) {
                        $this->logger->error('[ProductManager] Error al eliminar producto ID ' . $product->getId() . ': ' . $e->getMessage());
                    }
                }

                // Liberar memoria
                $collection->clear();
            } while ($currentBatchSize > 0);

            $this->logger->info('[ProductManager] Total productos eliminados: ' . $deletedCount);
        });
    }
}        