<?php

declare(strict_types=1);

namespace Atelier\MOSSetup\Model;

use Atelier\MOSSetup\Logger\CustomLogger;
use Atelier\MOSSetup\Helper\SecureContextExecutor;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;

class CategoryManager
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly CategoryResource $resourceCategory,
        private readonly CustomLogger $logger,
        private readonly SecureContextExecutor $secureContextExecutor,
        private readonly File $fileDriver,
        private readonly CategoryInterfaceFactory $categoryFactory,
    ) {}
    
    /**
     * Crea categorías jerárquicas a partir de un archivo JSON o CSV
     *
     * @param string $path Ruta del archivo
     * @param string $source Tipo de archivo (json o csv)
     * @return void
     */    
    public function createCategories(string $path, string $source): void 
    {
        $this->logger->info('[CategoryManager] Inicia método createCategories', [
            'path' => $path,
            'source' => $source
        ]);

        $this->secureContextExecutor->execute(function () use ($path, $source): void {
            try {
                /** @var \Magento\Store\Model\Store $store */
                $store = $this->storeManager->getStore();
                $rootCategoryId = (int) $store->getRootCategoryId();
                $this->logger->info('[CategoryManager] Root category ID obtenido', [
                    'rootCategoryId' => $rootCategoryId
                ]);

                $this->categoryRepository->get($rootCategoryId); // Validación

                if ($path !== null) {
                    $this->logger->info('[CategoryManager] Procesando contenido de archivo', [
                        'path' => $path
                    ]);

                    $hierarchicalCategories = $this->parseSourceFile($source, $path);

                    $this->logger->info('[CategoryManager] Categorías de primer nivel detectadas', [
                        'cantidad' => count($hierarchicalCategories)
                    ]);

                    foreach ($hierarchicalCategories as $level1Category) {
                        $level1Name = $level1Category['name'] ?? $level1Category['nombre_nivel1'] ?? "Categoría sin nombre";
                        $level1Code = $level1Category['code'] ?? $level1Category['codigo_nivel1'] ?? "Categoría sin código";
                        $level1UrlKey = $this->generateUrlKey($level1Name);

                        $this->logger->info('[CategoryManager] Se va a crear la categoría de primer nivel', [
                            'nombre' => $level1Name,
                            'codigo' => $level1Code,
                            'url_key' => $level1UrlKey
                        ]);

                        $parentCategory = $this->createCategory(
                            $level1Name,
                            $level1Code,
                            $rootCategoryId,
                            $level1UrlKey
                        );

                        $level2Categories = $level1Category['subcategories'] ?? $level1Category['subcategorias'] ?? [];
                        foreach ($level2Categories as $level2Category) {
                            $level2Name = $level2Category['name'] ?? $level2Category['nombre_nivel2'] ?? "Subcategoría sin nombre";
                            $level2Code = $level2Category['code'] ?? $level2Category['codigo_nivel2'] ?? "Subcategoría sin código";
                            $level2UrlKey = $this->generateUrlKey($level1UrlKey . '-' . $level2Name);

                            $this->logger->info('[CategoryManager] Se va a crear la subcategoría de segundo nivel', [
                                'nombre' => $level2Name,
                                'codigo' => $level2Code,
                                'url_key' => $level2UrlKey
                            ]);

                            $level2Parent = $this->createCategory(
                                $level2Name,
                                $level2Code,
                                (int)$parentCategory->getId(),
                                $level2UrlKey
                            );

                            $level3Categories = $level2Category['subcategories'] ?? $level2Category['subcategorias'] ?? [];
                            foreach ($level3Categories as $level3Category) {
                                $level3Name = $level3Category['name'] ?? $level3Category['nombre_nivel3'] ?? "Categoría de tercer nivel sin nombre";
                                $level3Code = $level3Category['code'] ?? $level3Category['codigo_nivel3'] ?? "Categoría sin código";
                                $level3UrlKey = $this->generateUrlKey($level2UrlKey . '-' . $level3Name);

                                $this->logger->info('[CategoryManager] Se va a crear la subcategoría de tercer nivel', [
                                    'nombre' => $level3Name,
                                    'codigo' => $level3Code,
                                    'url_key' => $level3UrlKey
                                ]);

                                $this->createCategory(
                                    $level3Name,
                                    $level3Code,
                                    (int)$level2Parent->getId(),
                                    $level3UrlKey
                                );
                            }
                        }
                    }
                } else {
                    $this->logger->info('[CategoryManager] No se proporcionó archivo, se omite creación jerárquica');
                    // Código alternativo si aplica
                }
            } catch (NoSuchEntityException $e) {
                $this->logger->error('[CategoryManager] Error al cargar categoría raíz', [
                    'error' => $e->getMessage()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('[CategoryManager] Error al crear categorías', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $this->logger->info('[CategoryManager] Fin del proceso de creación de categorías');
        });
    }

    // Método auxiliar para generar URL key
    private function generateUrlKey(string $name): string 
    {
        // Remover acentos
        $urlKey = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        
        // Convertir a minúsculas
        $urlKey = strtolower($urlKey);
        
        // Reemplazar espacios y caracteres no permitidos
        $urlKey = preg_replace('/[^a-z0-9-]/', '-', $urlKey);
        
        // Eliminar guiones múltiples
        $urlKey = preg_replace('/-+/', '-', $urlKey);
        
        // Eliminar guiones al inicio y final
        $urlKey = trim($urlKey, '-');
        
        return $urlKey;
    }

    // Método modificado para establecer category_code
    private function createCategory(
        string $name, 
        string $code, 
        int $parentId, 
        ?string $urlKey = null
    ): CategoryInterface 
    {
        try {
            /** @var \Magento\Catalog\Model\Category $category */
            $category = $this->categoryFactory->create();
            $category->setName($name);
            $category->setParentId($parentId);
            $category->setIsActive(true);
            
            // Establecer URL key
            if ($urlKey) {
                $category->setUrlKey($urlKey);
            }

            // Establecer category_code como atributo personalizado
            $category->setCustomAttribute('category_code', $code);
            
            $category_creada = $this->categoryRepository->save($category);

            $this->logger->info('[CategoryManager] Se crea categoría', [
                'category_id' => $category->getId(),
                'category_code' => $code
            ]);

            return $category_creada;

        } catch (\Exception $e) {
            $this->logger->error('[CategoryManager] Error al crear categoría: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse JSON file with hierarchical category structure
     *
     * @param string $fileContent
     * @return array
     */
    private function parseJsonFile(string $fileContent): array
    {
        $this->logger->debug('[CategoryManager] Contenido bruto del archivo JSON:', [
            'fileContent' => $fileContent
        ]);

        $parsedJson = json_decode($fileContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('[CategoryManager] Error al parsear el JSON', [
                'json_error' => json_last_error_msg()
            ]);
            throw new \Exception('[CategoryManager] Error al parsear el JSON: ' . json_last_error_msg());
        }

        if (!is_array($parsedJson)) {
            $this->logger->error('[CategoryManager] La estructura del JSON no es válida (no es un array)', [
                'parsedJson' => $parsedJson
            ]);
            throw new \Exception('[CategoryManager] La estructura del JSON no es válida (no es un array)');
        }

        $this->logger->debug('[CategoryManager] JSON parseado correctamente', [
            'parsed_categories' => $parsedJson['categories'] ?? []
        ]);

        return $parsedJson['categories'] ?? [];
    }

    /**
     * Parse source file based on type
     *
     * @param string $sourceType
     * @param string $filePath
     * @return array
     * @throws LocalizedException
     */
    public function parseSourceFile(string $sourceType, string $filePath): array
    {
        $fullPath = BP . '/' . $filePath;
        
        if (!$this->fileDriver->isExists($fullPath)) {
            throw new LocalizedException(__("[CategoryManager] Fichero no encontrado: %1", $fullPath));
        }

        $fileContent = $this->fileDriver->fileGetContents($fullPath);

        return match ($sourceType) {
            'json' => $this->parseJsonFile($fileContent),
            'csv' => $this->parseCsvFile($fileContent),
            default => throw new LocalizedException(__("[CategoryManager] Tipo de fichero incorrecto. Usa 'json' or 'csv'."))
        };
    }

    /**
     * Parse CSV file with hierarchical category structure
     *
     * @param string $fileContent
     * @return array
     */
    private function parseCsvFile(string $fileContent): array
    {
        $lines = array_map('str_getcsv', explode("\n", $fileContent));
        $headers = array_shift($lines);
        
        $categories = [];
        $currentLevel1 = $currentLevel2 = null;

        foreach ($lines as $line) {
            if (empty(array_filter($line))) continue;

            $categoryData = array_combine($headers, $line);

            // Nivel 1
            if (!$currentLevel1 || $categoryData['codigo_nivel1'] !== $currentLevel1['code']) {
                $currentLevel1 = [
                    'code' => $categoryData['codigo_nivel1'],
                    'name' => $categoryData['nombre_nivel1'],
                    'url_key' => $categoryData['url_key_nivel1'],
                    'subcategories' => []
                ];
                $categories[] = &$currentLevel1;
            }

            // Nivel 2
            if (!$currentLevel2 || $categoryData['codigo_nivel2'] !== $currentLevel2['code']) {
                $currentLevel2 = [
                    'code' => $categoryData['codigo_nivel2'],
                    'name' => $categoryData['nombre_nivel2'],
                    'url_key' => $categoryData['url_key_nivel2'],
                    'subcategories' => []
                ];
                $currentLevel1['subcategories'][] = $currentLevel2;
            }

            // Nivel 3
            $level3Category = [
                'code' => $categoryData['codigo_nivel3'],
                'name' => $categoryData['nombre_nivel3'],
                'url_key' => $categoryData['url_key_nivel3']
            ];
            $currentLevel2['subcategories'][] = $level3Category;
        }

        return $categories;
    }

    /**
     * Borrar una categoría específica por ID
     *
     * @param int $categoryId
     * @param bool $force
     * @return void
     */
    public function deleteSingleCategory(int $categoryId, bool $force = false): void
    {
        try {
            // Verificar si es una categoría protegida
            if ($this->isProtectedCategory($categoryId)) {
                $this->logger->error('[CategoryManager] La categoría ID ' . $categoryId . ' es una categoría protegida y no puede ser eliminada.');
                return;
            }
            
            // Usar el repositorio para obtener la categoría
            try {
                $category = $this->categoryRepository->get($categoryId);
            } catch (NoSuchEntityException $e) {
                $this->logger->error('[CategoryManager] La categoría ID ' . $categoryId . ' no existe.');
                return;
            }
            
            $categoryName = $category->getName();
            
            if ($force) {
                // Desasociar productos
                $this->removeProductsFromCategory($category);
                
                // Eliminar subcategorías primero
                $this->deleteSubcategories($category, $force);
            }
            
            try {
                $this->categoryRepository->delete($category);
                $this->logger->info('<info>Categoría ID ' . $categoryId . ' (' . $categoryName . ') eliminada correctamente.');
            } catch (\Exception $e) {
                $this->logger->error('[CategoryManager] Error al eliminar categoría ID ' . $categoryId . ': ' . $e->getMessage() . '');
                if (!$force) {
                    $this->logger->error('<comment>Intente usar la opción --force para eliminar subcategorías y desasociar productos primero.</comment>');
                }
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->error('[CategoryManager] La categoría ID ' . $categoryId . ' no existe.');
        } catch (StateException $e) {
            $this->logger->error('[CategoryManager] No se puede eliminar la categoría ID ' . $categoryId . ': ' . $e->getMessage() . '');
            if (!$force) {
                $this->logger->warning('<comment>Intente usar la opción --forzado para eliminar subcategorías y desasociar productos primero.</comment>');
            }
        }
    }
    
    /**
     * Borrar todas las categorías excepto las protegidas
     *
     * @param bool $force
     * @return void
     */
    public function deleteAllCategories(bool $force = false): void
    {
        $this->secureContextExecutor->execute(function () use ($force): void {    
            // Primero identificar las categorías de nivel superior (excepto las protegidas)
            $rootCollection = $this->categoryCollectionFactory->create();
            $rootCollection->addAttributeToFilter('parent_id', 2); // Categorías bajo Default Category
            
            $total = $rootCollection->getSize();
            $deleted = 0;
            
            $this->logger->info('[CategoryManager] Iniciando eliminación de categorías de nivel superior y sus subcategorías...');
            
            // Eliminar primero las categorías de nivel superior
            foreach ($rootCollection as $category) {
                try {
                    $this->deleteSingleCategory((int) $category->getId(), $force);
                    $deleted++;
                } catch (\Exception $e) {
                    $this->logger->error('[CategoryManager] Error al eliminar categoría ID ' . $category->getId() . ': ' . $e->getMessage() . '');
                }
            }
            
            // Verificar si quedan categorías para eliminar
            $remainingCollection = $this->categoryCollectionFactory->create();
            $remainingCollection->addAttributeToFilter('entity_id', ['nin' => [1, 2]]);
            $remaining = $remainingCollection->getSize();
            
            if ($remaining > 0 && $force) {
                $this->logger->info('[CategoryManager] Eliminando ' . $remaining . ' categorías restantes...');
                
                foreach ($remainingCollection as $category) {
                    try {
                        $this->categoryRepository->delete($category);
                        $deleted++;
                        $this->logger->info('[CategoryManager] Categoría ID ' . $category->getId() . ' (' . $category->getName() . ') eliminada.');
                    } catch (\Exception $e) {
                        $this->logger->error('[CategoryManager] Error al eliminar categoría ID ' . $category->getId() . ': ' . $e->getMessage() . '');
                    }
                }
            }

            $this->logger->info('[CategoryManager] Proceso completado: ' . $deleted . ' categorías eliminadas.');
        });
    }
    
    /**
     * Borrar categorías hijas de un padre específico
     *
     * @param int $parentId
     * @param bool $force
     * @return void
     */
    public function deleteChildCategories(int $parentId, bool $force = false): void
    {
        try {
            // Verificar si la categoría padre existe
            try {
                $parent = $this->categoryRepository->get($parentId);
            } catch (NoSuchEntityException $e) {
                $this->logger->error('[CategoryManager] La categoría padre ID ' . $parentId . ' no existe.');
                return;
            }
            
            $collection = $this->categoryCollectionFactory->create();
            $collection->addAttributeToFilter('parent_id', $parentId);
            
            $total = $collection->getSize();
            $deleted = 0;
            
            $this->logger->info('[CategoryManager] Iniciando eliminación de ' . $total . ' categorías hijas de la categoría ID ' . $parentId . ' (' . $parent->getName() . ')...');
            
            foreach ($collection as $category) {
                try {
                    $this->deleteSingleCategory((int) $category->getId(), $force);
                    $deleted++;
                } catch (\Exception $e) {
                    $this->logger->error('[CategoryManager] Error al eliminar categoría ID ' . $category->getId() . ': ' . $e->getMessage() . '');
                }
            }
            
            $this->logger->info('[CategoryManager] Proceso completado: ' . $deleted . ' de ' . $total . ' categorías eliminadas.');
        } catch (NoSuchEntityException $e) {
            $this->logger->error('[CategoryManager] La categoría padre ID ' . $parentId . ' no existe.');
        }
    }
    
    /**
     * Eliminar subcategorías de una categoría
     *
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @param bool $force
     * @return void
     */
    private function deleteSubcategories(\Magento\Catalog\Api\Data\CategoryInterface $category, bool $force = false): void
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToFilter('parent_id', $category->getId());
        
        if ($collection->getSize() > 0) {
            $this->logger->info('[CategoryManager] Eliminando ' . $collection->getSize() . ' subcategorías de la categoría ID ' . $category->getId() . ' (' . $category->getName() . ')...');
            
            foreach ($collection as $subcategory) {
                try {
                    // Eliminar subcategorías recursivamente
                    $this->deleteSubcategories($subcategory, $force);
                    
                    // Desasociar productos
                    if ($force) {
                        $this->removeProductsFromCategory($subcategory);
                    }
                    
                    $this->categoryRepository->delete($subcategory);
                    $this->logger->info('[CategoryManager] Subcategoría ID ' . $subcategory->getId() . ' (' . $subcategory->getName() . ') eliminada.');
                } catch (\Exception $e) {
                    $this->logger->error('[CategoryManager] Error al eliminar subcategoría ID ' . $subcategory->getId() . ': ' . $e->getMessage() . '');
                }
            }
        }
    }
    
    /**
     * Desasociar productos de una categoría
     *
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @return void
     */
    private function removeProductsFromCategory(\Magento\Catalog\Api\Data\CategoryInterface $category): void
    {
        try {
            // Necesitamos el modelo de categoría para acceder a getProductCollection
            // Se usa el repository para obtener el modelo completo

            /** @var \Magento\Catalog\Model\Category $categoryModel */
            $categoryModel = $this->categoryFactory->create();
            $categoryResource = $categoryModel->getResource();
            $categoryResource->load($categoryModel, $category->getId());
            
            $categoryProducts = $categoryModel->getProductCollection();
            $productCount = $categoryProducts->getSize();
            
            if ($productCount > 0) {
                $this->logger->info('[CategoryManager] Desasociando ' . $productCount . ' productos de la categoría ID ' . $category->getId() . ' (' . $category->getName() . ')...');
                
                // Asignar productos vacíos y guardar
                /** @var \Magento\Catalog\Model\Category $category */
                $category->setPostedProducts([]);
                $this->categoryRepository->save($category);
                
                $this->logger->info('[CategoryManager] ' . $productCount . ' productos desasociados correctamente.');
            }
        } catch (\Exception $e) {
            $this->logger->error('[CategoryManager] Error al desasociar productos: ' . $e->getMessage() . '');
        }
    }
    
    /**
     * Verificar si es una categoría protegida (raíz o default)
     *
     * @param int $categoryId
     * @return bool
     */
    private function isProtectedCategory(int $categoryId): bool
    {
        return in_array($categoryId, [1, 2]);
    }
}        
