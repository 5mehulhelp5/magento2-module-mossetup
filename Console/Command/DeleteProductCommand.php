<?php
declare(strict_types=1);

namespace Atelier\MosSetup\Console\Command;

use Atelier\MosSetup\Model\ProductManager;
use Atelier\MosSetup\Model\SystemCleanManager;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\Console\Cli;

/**
 * Comando para eliminar productos del catálogo.
 */
class DeleteProductCommand extends Command
{
    public function __construct(
        private readonly ProductManager $productManager,
        private readonly SystemCleanManager $systemManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('atelier:fixture:borra-producto')
            ->setDescription('Elimina productos del catálogo')
            ->addOption(
                'id',
                'i',
                InputOption::VALUE_OPTIONAL,
                'ID de producto específico para borrar'
            )
            ->addOption(
                'todo',
                't',
                InputOption::VALUE_NONE,
                'Borrar todos los productos'
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Entra en borrar productos.</info>');
            
        try {
            if ($input->getOption('id')) {
                $productId = (int)$input->getOption('id');
                $this->productManager->deleteSingleProduct($productId);
            } elseif ($input->getOption('todo')) {
                $this->productManager->deleteAllProducts();
                
            } else {
                $output->writeln('<error>Debe especificar una opción: --id o --todo</error>');
                return Cli::RETURN_FAILURE;
            }

            $output->writeln('<info>Fin de borrar productos.</info>');
            return Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }
}