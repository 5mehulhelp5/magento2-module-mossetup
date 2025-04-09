<?php
namespace Atelier\MOSSetup\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Atelier\MOSSetup\Model\CustomerManager;

class CreateCustomerCommand extends Command
{
    private CustomerManager $customerManager;

    public function __construct(
        CustomerManager $customerManager
    ) {
        $this->customerManager = $customerManager;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('atelier:fixture:crea-cliente')
            ->setDescription('Crea clientes de prueba (y sus direcciones)');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln('<info>Iniciando creación de datos de catálogo...</info>');
            
            // Limpia primero
            // $this->customerManager->cleanCustomers();
            $output->writeln('<info>Clientes limpiados correctamente.</info>');
            
            // Crea productos
            $output->writeln('<info>Creando clientes...</info>');
            $this->customerManager->createCustomers();
            $output->writeln('<info>Clientes creados correctamente.</info>');
            
            $output->writeln('<info>Datos de clientes creados exitosamente.</info>');
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }
}