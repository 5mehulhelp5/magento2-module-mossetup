<?php
declare(strict_types=1);

namespace Atelier\MOSSetup\Console\Command;

use Atelier\MOSSetup\Model\FashionProductManager;
use Atelier\MOSSetup\Model\SystemCleanManager;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateFashionCommand extends Command
{
    private const ARGUMENT_COUNT = 'count';
    
    public function __construct(
        private readonly FashionProductManager $productManager,
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
        $this->setName('atelier:fixture:crea-producto-moda')
            ->setDescription('Crea productos de moda')
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