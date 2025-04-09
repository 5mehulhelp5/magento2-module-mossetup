<?php
namespace Atelier\MosSetup\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Atelier\MosSetup\Model\OrderManager;

class CreateOrderCommand extends Command
{
    private OrderManager $orderManager;

    public function __construct(
        OrderManager $orderManager
    ) {
        $this->orderManager = $orderManager;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('atelier:fixture:crea-pedido')
            ->setDescription('Crea pedidos de prueba');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln('<info>Iniciando creación de pedidos...</info>');
            
            // Limpia primero
            // $this->orderManager->cleanOrders();
            // $output->writeln('<info>pedidos limpiados correctamente.</info>');
            
            // Crea productos
            $output->writeln('<info>Creando pedidos...</info>');
            $this->orderManager->createOrders();
            $output->writeln('<info>Pedidos creados correctamente.</info>');
            
            $output->writeln('<info>Datos de pedidos creados exitosamente.</info>');
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }
}