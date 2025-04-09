<?php
namespace Atelier\MosSetup\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Atelier\MosSetup\Model\ProductManager;

class AddProductCommand extends Command
{
    private $productManager;

    public function __construct(
        ProductManager $productManager
    ) {
        $this->productManager = $productManager;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('atelier:fixture:add-producto')
            ->setDescription('Añade nuevos productos al catálogo existente');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln('<info>Añadiendo nuevos datos al catálogo existente...</info>');
            
            // Crea productos
            $output->writeln('<info>Añadiendo productos...</info>');
            $this->productManager->createProducts(false);
            $output->writeln('<info>Productos añadidos correctamente.</info>');
            
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }
}