<?php
namespace Atelier\MosSetup\Console\Command;

use Atelier\MosSetup\Model\ProductManager;
use Atelier\MosSetup\Model\SystemCleanManager;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateProductCommand extends Command
{
    public function __construct(
        private readonly ProductManager $productManager,
        private readonly SystemCleanManager $systemManager
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('atelier:fixture:crea-producto')
            ->setDescription('Crea productos de todos los tipos excepto configurables.');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln('<info>Iniciando creación de datos de catálogo...</info>');
            
            // Crea productos, de todo tipo excepto configurables
            $output->writeln('<info>Creando productos...</info>');
            $this->productManager->createProducts();
            $output->writeln('<info>Productos creados correctamente.</info>');

            $this->systemManager->reindex();
            
            $output->writeln('<info>Datos de catálogo creados exitosamente.</info>');
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }
}