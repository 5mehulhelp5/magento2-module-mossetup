<?php
declare(strict_types=1);

namespace Atelier\MosSetup\Console\Command;

use Atelier\MosSetup\Model\ProductManager;
use Atelier\MosSetup\Model\SystemCleanManager;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CreateConfigurableCommand
 * Comando para crear productos configurables en Magento 2.4.7 con PHP 8.2
 */
class CreateConfigurableCommand extends Command
{
    private const ARGUMENT_COUNT = 'count';
    
    public function __construct(
        private readonly ProductManager $productManager,
        private readonly SystemCleanManager $systemManager
    ) {
        parent::__construct();
    }

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('atelier:fixture:crea-configurable')
            ->setDescription('Crea productos configurables con variación de color y talla')
            ->addArgument(
                self::ARGUMENT_COUNT,
                InputArgument::OPTIONAL,
                'Número de productos configurables a crear',
                1
            );
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Número de productos a crear
            $count = (int)$input->getArgument(self::ARGUMENT_COUNT);
            
            if ($count < 1) {
                $output->writeln('<error>El número de productos debe ser al menos 1</error>');
                return Command::FAILURE;
            }
            
            $this->productManager->createConfigurableProducts($count);
            $this->systemManager->reindex();
            
            $output->writeln('<info>Proceso completado correctamente.</info>');
            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            $output->writeln('<error>Traza: ' . $e->getTraceAsString() . '</error>');
            return Command::FAILURE;
        }
    }
}