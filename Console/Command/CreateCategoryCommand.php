<?php
declare(strict_types=1);

namespace Atelier\MOSSetup\Console\Command;

use Atelier\MOSSetup\Model\CategoryManager;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

// bin/magento atelier:fixture:crea-categoria --source=json --file=path/to/categories.json
// bin/magento atelier:fixture:crea-categoria --source=csv --file=path/to/categories.csv

class CreateCategoryCommand extends Command
{
    private const SOURCE_TYPE = 'source';
    private const FILE_PATH = 'file';

    private CategoryManager $importManager;
    
    public function __construct(
        CategoryManager $importManager
    ) {
        $this->importManager = $importManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('atelier:fixture:crea-categoria')
            ->setDescription('Importa categorías de JSON o CSV')
            ->addOption(
                self::SOURCE_TYPE,
                null,
                InputOption::VALUE_REQUIRED,
                'Tipo de fichero (JSON o CSV)'
            )
            ->addOption(
                self::FILE_PATH,
                null,
                InputOption::VALUE_REQUIRED,
                'Ruta al fichero'
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceType = (string)$input->getOption(self::SOURCE_TYPE);
        $filePath = (string)$input->getOption(self::FILE_PATH);

        $output->writeln("<info>Entra en importar categorías: " .  $filePath . "</info>");

        try {
            $this->importManager->createCategories($filePath , $sourceType);
            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Cli::RETURN_FAILURE;
        }
    }
}