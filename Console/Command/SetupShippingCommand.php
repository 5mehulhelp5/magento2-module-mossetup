<?php
namespace Atelier\MOSSetup\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Atelier\MOSSetup\Model\ShippingManager;

class SetupShippingCommand extends Command
{
    private const FILE_PATH = 'file';
    
    /**
     * @param ShippingManager $shippingManager
     */
    public function __construct(
        private readonly ShippingManager $shippingManager
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('atelier:setup:shipping')
            ->setDescription('Configura los métodos de envío')
            ->addOption(
                self::FILE_PATH,
                'c',
                InputOption::VALUE_REQUIRED,
                'Ruta al fichero CSV'
            )
            ;
        
        return parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Configurando los gastos de envío...</info>');
        $csvFile = $input->getOption(self::FILE_PATH);
        
        if ($csvFile && file_exists($csvFile)) {
            
            $output->writeln("<info>Importando el fichero: {$csvFile}</info>");
            $result = $this->shippingManager->importRatesFromCsv($csvFile);
            $output->writeln("<info>Importadas {$result['success']} tarifas de envío.</info>");
            
            if (isset($result['errors']) && $result['errors'] > 0) { 
                $output->writeln("<error>Error al importar {$result['errors']} tarifas de envío.</error>");
            }
        } 
        else {
            $output->writeln("<info>No se ha procesado nada.</info>"); 
        }
        return Command::SUCCESS;
    }
}