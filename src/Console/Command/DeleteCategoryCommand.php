<?php
namespace Atelier\MosSetup\Console\Command;

use Atelier\MosSetup\Model\CategoryManager;
use Atelier\MosSetup\Model\SystemCleanManager;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class DeleteCategoryCommand extends Command
{
    public function __construct(
        private readonly CategoryManager $categoryManager,
        private readonly SystemCleanManager $systemManager
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('atelier:fixture:borra-categoria')
            ->setDescription('Borra las categorías')
            ->addOption(
                'id',
                'c',
                InputOption::VALUE_OPTIONAL,
                'ID de categoría específica para borrar'
            )
            ->addOption(
                'todo',
                't',
                InputOption::VALUE_NONE,
                'Borrar todas las categorías excepto la raíz y por defecto'
            )
            ->addOption(
                'padre',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Borrar categorías bajo un padre específico'
            )
            ->addOption(
                'forzado',
                'f',
                InputOption::VALUE_NONE,
                'Forzar el borrado desasociando productos y eliminando subcategorías'
            );
        
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {

            $output->writeln('<info>Entra en borrar categorías.</info>');
            
            $categoryId = $input->getOption('id');
            $deleteAll = $input->getOption('todo');
            $parentId = $input->getOption('padre');
            $force = $input->getOption('forzado');
            
            if ($categoryId) {
                // Borrar una categoría específica
                $this->categoryManager->deleteSingleCategory((int)$categoryId, (bool)$force);
            } elseif ($deleteAll) {
                // Borrar todas las categorías excepto las protegidas
                $this->categoryManager->deleteAllCategories((bool)$force);
            } elseif ($parentId) {
                // Borrar categorías bajo un padre específico
                $$this->categoryManager->deleteChildCategories((int)$parentId, (bool)$force);
            } else {
                $output->writeln('<error>Debe especificar una opción: --id, --todo o --padre</error>');
                return \Magento\Framework\Console\Cli::RETURN_FAILURE;
            }

            $output->writeln('<info>Fin de borrar categorías</info>');
            
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    } 
}