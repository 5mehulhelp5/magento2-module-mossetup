<?php
declare(strict_types=1);

namespace Atelier\MosSetup\Model;

use Atelier\MosSetup\Logger\CustomLogger;
use Atelier\MosSetup\Helper\SecureContextExecutor;
use Atelier\MosSetup\Model\SystemCleanManager;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\TablerateFactory;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\File\Csv;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory;


class ShippingManager
{
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly TablerateFactory $tablerateFactory,
        private readonly DirectoryList $directoryList,
        private readonly Csv $csvProcessor,
        private readonly CustomLogger $logger,
        private readonly SecureContextExecutor $secureContextExecutor,
        private readonly CollectionFactory $regionCollectionFactory,
        private readonly SystemCleanManager $systemCleanManager
    ) {
    }
    
    /**
     * Import shipping rates from CSV file
     * 
     * @param string $csvFile Path to CSV file
     * @return array Results of import process
     */
    public function importRatesFromCsv($csvFile)
    {
        $this->logger->info('Entra en importRatesFromCsv: ' . $csvFile);
            
        $result = [
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        try {
            $this->secureContextExecutor->execute(function() use ($csvFile, &$result) {
                
                // Asegurar que está activo y habilitado
                if (!$this->isTableRateInstalled()) {
                    throw new \Exception('[ShippingManager] Table Rate shipping module is not available.');
                }    
                
                // Get website ID
                $websiteId = $this->storeManager->getWebsite()->getId();
                
                $this->configWriter->save(
                    'carriers/tablerate/active',
                    '1',
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT
                );

                // Set condition to "weight" since the CSV is based on weight
                $this->configWriter->save(
                    'carriers/tablerate/condition_name',
                    'package_value_with_discount',
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT
                );
                
                $this->configWriter->save('carriers/tablerate/title', 'Estándar', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
                $this->configWriter->save('carriers/tablerate/name', 'Envío estándar', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
                $this->configWriter->save('carriers/tablerate/specificerrmsg', 'Este método no está disponible. Por favor selecciona otro.', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
                
                // Prepare CSV file for import
                if (!file_exists($csvFile)) {
                    throw new \Exception("[ShippingManager] CSV file not found: $csvFile");
                }

                /** @var Tablerate $tablerateResource */
                $tablerateResource = $this->getTablerateResource();

                $csvData = file_get_contents($csvFile);
                $rows = explode("\n", $csvData);

                // Omitir la primera fila (encabezados)
                array_shift($rows);

                $connection = $tablerateResource->getConnection();
                $tableName = $tablerateResource->getMainTable();

                // Limpiar la tabla primero
                $connection->delete($tableName, ['website_id = ?' => $websiteId]);

                $insertedRows = 0;
                $errors = 0;

                foreach ($rows as $rowNum => $row) {
                    // Saltarse líneas vacías
                    if (empty(trim($row))) {
                        continue;
                    }
                    
                    // Analizar la fila CSV
                    $data = str_getcsv($row, ',', '"');
                    $this->logger->info('[ShippingManager] Processing row: ' . json_encode($data));
                    
                    // Verificar que tengamos suficientes campos
                    if (count($data) < 5) {
                        $this->logger->error('[ShippingManager] Row ' . ($rowNum + 1) . ' does not have enough fields: ' . $row);
                        $errors++;
                        continue;
                    }
                    
                    // Mapear campos del CSV a la estructura de la tabla
                    list($country, $region, $zip, $weight, $price) = $data;
                    
                    // Convertir región de texto a ID si es necesario
                    $regionId = $this->getRegionId($region, $country);
                    // Convertir asteriscos a valores apropiados
                    $countryId = ($country === '*') ? '0' : $country;
                    $zipCode = ($zip === '*') ? '*' : $zip;
                    
                    try {
                        // Crear el array de datos para insertar
                        $insertData = [
                            'website_id' => $websiteId,
                            'dest_country_id' => $countryId,
                            'dest_region_id' => $regionId,
                            'dest_zip' => $zipCode,
                            'condition_name' => 'package_value_with_discount',
                            'condition_value' => (float)$weight,
                            'price' => (float)$price,
                            'cost' => 0
                        ];
        
                        // Insertar en la base de datos
                        $connection->insert($tableName, $insertData);
                        $insertedRows++;
                            } catch (\Exception $e) {
                                $this->logger->error('[ShippingManager] Error al añadir fila: ' . ($rowNum + 1) . ': ' . $e->getMessage());
                                $errors++;
                            }
                        }

                        $this->logger->info("[ShippingManager] Importación completada. Insertadas: {$insertedRows}, Errores: {$errors}");

                        // Actualizar resultado
                        $result['success'] = $insertedRows;
                        $result['errors'] = $errors;

                // Log the import result
                $this->logger->info("[ShippingManager] Shipping rates importadas: {$result['success']}, Errors: {$result['errors']}");
            
            });
            
            $this->systemCleanManager->cleanCache();

            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('[ShippingManager] Error al importar los gastos de envío: ' . $e->getMessage());
            $result['errors']++;
            $result['details'][] = $e->getMessage();
            return $result;
        }
    }

    /**
     * Get region ID from region name and country ID
     *
     * @param string $regionName Region name
     * @param string $countryId Country ID (e.g. 'ES')
     * @return int Region ID or 0 if not found
     */
    public function getRegionId($regionName, $countryId)
    {
        if ($regionName === '*' || empty($regionName) || $countryId === '*' || empty($countryId)) {
            return 0;
        }
        
        try {
            // Use Magento's region collection to get the region ID
            $regionCollection = $this->regionCollectionFactory->create();
            $region = $regionCollection
                ->addFieldToFilter('country_id', $countryId)
                ->addFieldToFilter(['name', 'default_name'], [
                    ['like' => $regionName],
                    ['like' => $regionName]
                ])
                ->getFirstItem();
            
            $this->logger->info('[ShippingManager] Buscar la región por país:' . $countryId . ', Region=' . $regionName . ', Found ID=' . $region->getId());
            
            return $region->getId() ?: 0;
        } catch (\Exception $e) {
            $this->logger->error('[ShippingManager] Error al obtener el ID de región: ' . $e->getMessage());
            return 0;
        }
    }
     
    /**
     * Check if tablerate shipping is installed
     * 
     * @return bool
     */
    private function isTableRateInstalled()
    {
        try {
            $this->getTablerateResource();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get tablerate resource model
     * 
     * @return Tablerate
     */
    private function getTablerateResource()
    {
        return $this->tablerateFactory->create();
    }
}