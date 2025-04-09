<?php

declare(strict_types=1);

namespace Atelier\MOSSetup\Helper;

use Atelier\MOSSetup\Logger\CustomLogger;

use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Registry;

class SecureContextExecutor
{
    public function __construct(
        private readonly State $appState,
        private readonly CustomLogger $logger,
        private readonly Registry $registry
    ) {}

    public function execute(callable $callback): mixed
    {
        try {
            $currentArea = null;
            try {
                $currentArea = $this->appState->getAreaCode();
            } catch (LocalizedException $e) {
                // Sin área definida, válido en CLI
            }
            
            if ($currentArea === Area::AREA_ADMINHTML) {
                return $callback();
            }
            
            // Emular el área de administración
            return $this->appState->emulateAreaCode(
                Area::AREA_ADMINHTML,
                function() use ($callback) {
                    
                    $this->registry->register('isSecureArea', true);
                    
                    // Configurar explícitamente el área segura para operaciones restringidas
                    $objectManager = ObjectManager::getInstance();
                    $state = $objectManager->get(State::class);
                    
                    // Guardar el estado original
                    $isSecureAreaOriginal = $state->isAreaCodeEmulated();
                    
                    // Establecer área segura
                    $reflection = new \ReflectionClass($state);
                    $property = $reflection->getProperty('_isAreaCodeEmulated');
                    $property->setAccessible(true);
                    $property->setValue($state, true);
                    
                    try {
                        return $callback();
                    } finally {
                        // Restaurar al estado original
                        $property->setValue($state, $isSecureAreaOriginal);
                    }
                }
            );
        } catch (\Exception $e) {
            $this->logger->error('Error ejecutando en contexto seguro', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

}