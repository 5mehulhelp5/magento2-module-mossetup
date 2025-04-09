<?php

namespace Atelier\MosSetup\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\App\State;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;

class ConfigureAttributesCommand extends Command
{
    /**
     * @var State
     */
    private $state;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * Constructor
     *
     * @param State $state
     * @param EavSetupFactory $eavSetupFactory
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        State $state,
        EavSetupFactory $eavSetupFactory,
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->state = $state;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->moduleDataSetup = $moduleDataSetup;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('atelier:setup:atributo')
            ->setDescription('Configura los atributos size y color para productos configurables')
            ->addOption(
                'attribute',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Especifica qué atributo configurar (size, color o ambos)',
                'all'
            );

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            // Establecer el área para evitar errores
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);
            
            $attributeOption = $input->getOption('attribute');
            
            if ($attributeOption == 'all' || $attributeOption == 'size') {
                $this->configureSizeAttribute($output);
            }
            
            if ($attributeOption == 'all' || $attributeOption == 'color') {
                $this->configureColorAttribute($output);
            }
            
            $output->writeln('<info>Configuración de atributos completada exitosamente.</info>');
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }

    /**
     * Configura el atributo size
     *
     * @param OutputInterface $output
     * @return void
     */
    private function configureSizeAttribute(OutputInterface $output)
    {
        $output->writeln('<info>Configurando atributo size...</info>');
        
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // Definir las tallas
        $sizes = [
            'XXS' => 'XXS',
            'XS' => 'XS',
            'S' => 'S',
            'M' => 'M',
            'L' => 'L',
            'XL' => 'XL',
            'TR34' => '34',
            'TR36' => '36',
            'TR38' => '38',
            'TZ36' => '36',
            'TZ37' => '37'
        ];

        // Verificar si el atributo size existe
        $attributeId = $eavSetup->getAttributeId(Product::ENTITY, 'size');
        
        if (!$attributeId) {
            // Si no existe, crearlo con las opciones de talla
            $output->writeln('Creando atributo size con tallas: XXS, XS, S, M, L, XL...');
            $eavSetup->addAttribute(
                Product::ENTITY,
                'size',
                [
                    'type' => 'varchar',
                    'label' => 'Size',
                    'input' => 'select',
                    'source' => '',
                    'frontend' => '',
                    'required' => false,
                    'backend' => '',
                    'sort_order' => 50,
                    'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'default' => null,
                    'visible' => true,
                    'user_defined' => true,
                    'searchable' => true,
                    'filterable' => true,
                    'comparable' => true,
                    'visible_on_front' => true,
                    'unique' => false,
                    'apply_to' => '',
                    'group' => 'General',
                    'used_in_product_listing' => true,
                    'is_used_in_grid' => true,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => true,
                    'is_html_allowed_on_front' => false,
                    'option' => [
                        'values' => array_values($sizes)
                    ]
                ]
            );
        } else {
            // Si existe, actualizarlo para ser usable en configurables
            $output->writeln('Actualizando atributo size existente...');
            $eavSetup->updateAttribute(
                Product::ENTITY,
                'size',
                'is_global',
                ScopedAttributeInterface::SCOPE_GLOBAL
            );
            
            // Eliminar todas las opciones existentes y añadir las nuevas
            $output->writeln('Actualizando opciones de talla...');
            
            // Obtener la información del atributo
            $attribute = $eavSetup->getAttribute(Product::ENTITY, 'size');
            
            // Eliminar las opciones existentes
            // Necesitamos acceder directamente a la tabla para eliminar las opciones
            $tableName = $this->moduleDataSetup->getTable('eav_attribute_option');
            $this->moduleDataSetup->getConnection()->delete(
                $tableName,
                ['attribute_id = ?' => $attribute['attribute_id']]
            );
            
            $output->writeln('Opciones de talla anteriores eliminadas.');
            
            // Añadir las nuevas opciones
            $options = [];
            $options['attribute_id'] = $attribute['attribute_id'];
            $options['values'] = array_values($sizes);
            
            $eavSetup->addAttributeOption($options);
            $output->writeln('Nuevas opciones de talla (XXS, XS, S, M, L, XL) añadidas.');
        }

        // Asegurarse de que el atributo está en el attribute set default
        $attributeSetId = $eavSetup->getDefaultAttributeSetId(Product::ENTITY);
        $attributeGroupId = $eavSetup->getDefaultAttributeGroupId(Product::ENTITY, $attributeSetId);
        
        $attributeId = $eavSetup->getAttributeId(Product::ENTITY, 'size');
        
        // Agregar al attribute set default si no está ya
        $output->writeln('Añadiendo atributo size al attribute set default...');
        $eavSetup->addAttributeToGroup(
            Product::ENTITY,
            $attributeSetId,
            $attributeGroupId,
            $attributeId,
            50
        );
        
        // Hacer que el atributo sea usado para crear productos configurables
        $output->writeln('Configurando atributo size para usar en productos configurables...');
        $eavSetup->updateAttribute(
            Product::ENTITY,
            'size',
            'is_configurable',
            1
        );
        
        $output->writeln('<info>Atributo size configurado correctamente con las tallas: XXS, XS, S, M, L, XL</info>');
    }

    /**
     * Configura el atributo color
     *
     * @param OutputInterface $output
     * @return void
     */
    private function configureColorAttribute(OutputInterface $output)
    {
        $output->writeln('<info>Configurando atributo color...</info>');
        
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // Definir los colores
        $colors = [
            'Amarillo' => 'Amarillo',
            'Azul celeste' => 'Azul celeste',
            'Azul marino' => 'Azul marino',
            'Azul pato' => 'Azul pato',
            'Azul real' => 'Azul real',
            'Blanco' => 'Blanco',
            'Marrón' => 'Marrón',
            'Naranja' => 'Naranja',
            'Negro' => 'Negro',
            'Rojo' => 'Rojo',
            'Rosa' => 'Rosa',
            'Verde caqui' => 'Verde caqui',
            'Verde bosque' => 'Verde bosque',
            'Verde esmeralda' => 'Verde esmeralda',
            'Verde lima' => 'Verde lima',
            'Verde menta' => 'Verde menta',
            'Violeta' => 'Violeta',
        ];

        // Verificar si el atributo color existe
        $attributeId = $eavSetup->getAttributeId(Product::ENTITY, 'color');
        
        if (!$attributeId) {
            // Si no existe, crearlo
            $output->writeln('Creando atributo color...');
            $eavSetup->addAttribute(
                Product::ENTITY,
                'color',
                [
                    'type' => 'varchar',
                    'label' => 'Color',
                    'input' => 'select',
                    'source' => '',
                    'frontend' => '',
                    'required' => false,
                    'backend' => '',
                    'sort_order' => 40,
                    'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'default' => null,
                    'visible' => true,
                    'user_defined' => true,
                    'searchable' => true,
                    'filterable' => true,
                    'comparable' => true,
                    'visible_on_front' => true,
                    'unique' => false,
                    'apply_to' => '',
                    'group' => 'General',
                    'used_in_product_listing' => true,
                    'is_used_in_grid' => true,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => true,
                    'is_html_allowed_on_front' => false,
                    'option' => [
                        'values' => array_values($colors)
                    ]
                ]
            );
        } else {
            // Si existe, actualizar sus opciones
            $output->writeln('Actualizando atributo color existente...');
            $eavSetup->updateAttribute(
                Product::ENTITY,
                'color',
                'is_global',
                ScopedAttributeInterface::SCOPE_GLOBAL
            );

            // Eliminar todas las opciones existentes y añadir las nuevas
            $output->writeln('Actualizando opciones de color...');
            
            // Obtener la información del atributo
            $attribute = $eavSetup->getAttribute(Product::ENTITY, 'color');
            
            // Eliminar las opciones existentes
            // Necesitamos acceder directamente a la tabla para eliminar las opciones
            $tableName = $this->moduleDataSetup->getTable('eav_attribute_option');
            $this->moduleDataSetup->getConnection()->delete(
                $tableName,
                ['attribute_id = ?' => $attribute['attribute_id']]
            );
            
            $output->writeln('Opciones de color anteriores eliminadas.');
            
            // Añadir los valores de color
            $output->writeln('Actualizando opciones de color...');
            $attribute = $eavSetup->getAttribute(Product::ENTITY, 'color');
            $options = [];
            $options['attribute_id'] = $attribute['attribute_id'];
            $options['values'] = array_values($colors);
            
            $eavSetup->addAttributeOption($options);
        }

        // Asegurarse de que el atributo está en el attribute set default
        $attributeSetId = $eavSetup->getDefaultAttributeSetId(Product::ENTITY);
        $attributeGroupId = $eavSetup->getDefaultAttributeGroupId(Product::ENTITY, $attributeSetId);
        
        $attributeId = $eavSetup->getAttributeId(Product::ENTITY, 'color');
        
        // Agregar al attribute set default si no está ya
        $output->writeln('Añadiendo atributo color al attribute set default...');
        $eavSetup->addAttributeToGroup(
            Product::ENTITY,
            $attributeSetId,
            $attributeGroupId,
            $attributeId,
            40
        );
        
        // Hacer que el atributo sea usado para crear productos configurables
        $output->writeln('Configurando atributo color para usar en productos configurables...');
        $eavSetup->updateAttribute(
            Product::ENTITY,
            'color',
            'is_configurable',
            1
        );
        
        $output->writeln('<info>Atributo color configurado correctamente.</info>');
    }
}