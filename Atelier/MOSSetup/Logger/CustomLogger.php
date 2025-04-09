<?php
namespace  Atelier\MOSSetup\Logger;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class CustomLogger extends Logger
{
    /**
     * Constructor para configurar el logger personalizado
     * 
     * @param string $name Nombre del logger
     * @param string|null $logFile Ruta del archivo de log (opcional)
     */
    public function __construct(
        $name = 'custom_logger', 
        $logFile = null
    ) {
        parent::__construct($name);

        // Si no se proporciona ruta de archivo, usar ruta por defecto de Magento
        if ($logFile === null) {
            $logFile = BP . '/var/log/atelier.log';
        }

        // Añadir StreamHandler para escribir logs en archivo
        $this->pushHandler(
            new StreamHandler(
                $logFile, 
                Logger::DEBUG  // Nivel de log (puede ajustarse)
            )
        );
    }
}