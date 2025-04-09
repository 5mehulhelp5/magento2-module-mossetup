<?php
declare(strict_types=1);

namespace Atelier\MOSSetup\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

/**
 * Clase que instala datos de ejemplo copiando varios archivos desde el módulo a var/import.
 */
class InstallSampleData implements DataPatchInterface
{
    /**
     * Lista de archivos a copiar desde el módulo a var/import.
     * Clave: nombre del archivo dentro de fixtures.
     * Valor: nombre de destino en var/import (puede ser el mismo).
     */
    private array $filesToCopy = [
        'categorias_moda.json' => 'categorias_moda.json',
        'shipping_importe.csv' => 'shipping_importe.csv',
        'shipping_peso.csv' => 'shipping_peso.csv',
    ];

    /**
     * Constructor que utiliza inyección de dependencias moderna.
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly File $fileIo,
        private readonly ModuleDirReader $moduleDirReader
    ) {}

    /**
     * Aplica el patch copiando los archivos definidos al directorio var/import.
     */
    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $moduleDir = $this->moduleDirReader->getModuleDir('', 'Atelier_MOSSetup');

        foreach ($this->filesToCopy as $sourceName => $destinationName) {
            $sourceFile = $moduleDir . '/Data/fixtures/' . $sourceName;
            $destination = BP . '/var/import/' . $destinationName;

            // Crea el directorio destino si no existe
            $this->fileIo->checkAndCreateFolder(dirname($destination));

            // Copia el archivo si existe
            if ($this->fileIo->fileExists($sourceFile)) {
                $this->fileIo->cp($sourceFile, $destination);
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * Devuelve las dependencias del patch.
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Devuelve los alias del patch.
     */
    public function getAliases(): array
    {
        return [];
    }
}