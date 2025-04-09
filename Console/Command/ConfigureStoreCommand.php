<?php
namespace Atelier\MOSSetup\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;
use Magento\InventoryApi\Api\SourceRepositoryInterface;

class ConfigureStoreCommand extends Command
{
    protected State $state;
    protected StoreManagerInterface $storeManager;
    protected WriterInterface $configWriter;
    protected ScopeConfigInterface $scopeConfig;
    protected TypeListInterface $cacheTypeList;
    protected CacheFrontendPool $cacheFrontendPool;
    protected SourceRepositoryInterface $sourceFactory; 

    public function __construct(
        State $state,
        StoreManagerInterface $storeManager,
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig,
        TypeListInterface $cacheTypeList,
        CacheFrontendPool $cacheFrontendPool,
        SourceRepositoryInterface $sourceFactory
    ) {
        $this->state = $state;
        $this->storeManager = $storeManager;
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->sourceFactory = $sourceFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('atelier:setup:store')
            ->setDescription('Configuración inicial de la tienda Magento 2.4.7');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Exception $e) {}

        
        $configFilePath = BP . '/var/store_config.json';

        $helper = new QuestionHelper();
        $loadFromFile = $helper->ask(
            $input,
            $output,
            new ConfirmationQuestion('¿Cargar configuración desde var/store_config.json? [y/N]: ', false)
        );

        if ($loadFromFile && file_exists($configFilePath)) {
            $jsonConfig = file_get_contents($configFilePath);
            $config = json_decode($jsonConfig, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $output->writeln('<error>Error en el formato JSON del fichero de configuración. Se continuará con preguntas manuales.</error>');
                $config = [];
            } else {
                $output->writeln('<info>Configuración cargada correctamente desde el fichero.</info>');
            }
        } else {
        
            $config = [];
            // Motor de búsqueda
            // $config['search_engine'] = 'opensearch'; // Por defecto OpenSearch
            // $config['opensearch_host'] = $helper->ask($input, $output, new Question('Host de OpenSearch (opensearch): ', 'opensearch'));
            // $config['opensearch_port'] = $helper->ask($input, $output, new Question('Puerto de OpenSearch (9200): ', '9200'));

            // Categoría raíz
            // $config['root_category_id'] = $helper->ask($input, $output, new Question('ID Categoría raíz (2): ', '2'));

            // URLs SEO
            // $config['use_rewrites'] = $helper->ask($input, $output, new ConfirmationQuestion('¿Usar URL amigables (SEO)? [Y/n]: ', true));
            // $config['base_url'] = $helper->ask($input, $output, new Question('Base URL (https://magento.test/): ', 'https://magento.test/'));

            // Localización y regional
            $config['default_country'] = $helper->ask($input, $output, new Question('País por defecto (ES): ', 'ES'));

            $config['locale'] = $helper->ask($input, $output, new Question('Código de idioma (es_ES): ', 'es_ES'));
            $config['timezone'] = $helper->ask($input, $output, new Question('Zona horaria (Europe/Madrid): ', 'Europe/Madrid'));
            $config['weight_unit'] = $helper->ask($input, $output, new ChoiceQuestion('Unidad de peso:', ['kgs', 'lbs'], 0));
            $config['firstday'] = $helper->ask($input, $output, new Question('Primer día de la semana (1=Lunes, 0=Domingo): ', '1'));

            // Información básica
            $config['store_name'] = $helper->ask($input, $output, new Question('Nombre de la tienda (Mi tienda): ', 'Mi tienda'));
            $config['store_hours'] = $helper->ask($input, $output, new Question('Horario de la tienda (L-V de 9 a 18h, V de 9 a 15h): ', 'L-V de 9 a 18h, V de 9 a 15h'));
            
            $config['store_phone'] = $helper->ask($input, $output, new Question('Teléfono de la tienda: ', '123 456 789'));
            $config['store_country_id'] = $helper->ask($input, $output, new Question('País tienda (ES): ', 'ES'));
            $config['store_region_id'] = $helper->ask($input, $output, new Question('ID región tienda (181 - Zaragoza): ', '181'));
            $config['store_postcode'] = $helper->ask($input, $output, new Question('Código postal tienda (50012): ', '50012'));
            $config['store_city'] = $helper->ask($input, $output, new Question('Ciudad tienda (Zaragoza): ', 'Zaragoza'));
            $config['store_street1'] = $helper->ask($input, $output, new Question('Dirección línea 1: ', 'Dirección línea 1'));
            $config['store_street2'] = $helper->ask($input, $output, new Question('Dirección línea 2: ', 'Dirección línea 2'));
            $config['merchant_vat'] = $helper->ask($input, $output, new Question('Número VAT del comerciante: ', 'B123456789'));

            // Divisa
            // Divisa base
            $currencyOptions = ['EUR', 'USD', 'GBP'];
            $config['currency_base'] = $helper->ask(
                $input,
                $output,
                new ChoiceQuestion('Divisa base:', $currencyOptions, 0)
            );

            // Divisas permitidas
            $config['currency_allowed'] = $helper->ask(
                $input,
                $output,
                (new ChoiceQuestion('Divisas permitidas (puedes seleccionar varias):', $currencyOptions, 0))
                    ->setMultiselect(true)
            );

            // Asegurar que la divisa base esté siempre incluida en permitidas
            if (!in_array($config['currency_base'], $config['currency_allowed'])) {
                $config['currency_allowed'][] = $config['currency_base'];
            }
            
            $config['trans_email'] = $helper->ask($input, $output, new Question('Email transaccional (info@mitienda.com): ', 'info@mitienda.com'));

            // Websites
            $numWebsites = (int)$helper->ask($input, $output, new Question('Número de websites (1): ', 1));
            $config['websites'] = [];
            for ($w = 1; $w <= $numWebsites; $w++) {
                $website = [];
                $website['name'] = $helper->ask($input, $output, new Question("Nombre del website #{$w}: ", "Website #{$w}"));

                $numStoreViews = (int)$helper->ask($input, $output, new Question("Número de store views para website #{$w} (1): ", 1));
                $website['store_views'] = [];
                for ($sv = 1; $sv <= $numStoreViews; $sv++) {
                    $storeView = [];
                    $storeView['name'] = $helper->ask($input, $output, new Question("Nombre del store view #{$sv}: ", "Store view #{$sv}"));
                    $storeView['language'] = $helper->ask($input, $output, new ChoiceQuestion("Idioma para store view #{$sv}:", ['ES','EN','FR','DE'], 0));
                    $storeView['currency'] = $helper->ask($input, $output, new ChoiceQuestion("Divisa para store view #{$sv}:", ['EUR','USD','GBP'], 0));
                    $website['store_views'][] = $storeView;
                }
                $config['websites'][] = $website;
            }
            
            $config['default_website'] = $helper->ask($input, $output, new Question('Website por defecto (nombre del primer website): ', $config['websites'][0]['name']));

            // CAPTCHA
            $captchaAreas = ['No usar', 'user_create', 'user_login', 'contact', 'forgotpassword', 'checkout'];
            $config['captcha'] = $helper->ask($input, $output, (new ChoiceQuestion('¿Dónde aplicar CAPTCHA?', $captchaAreas, 0))->setMultiselect(true));

            // 2FA
            $config['2fa'] = $helper->ask($input, $output, new ConfirmationQuestion('¿Habilitar autenticación de dos factores (2FA)? [y/N]: ', false));

            // Formas de pago
            $paymentMethods = ['Tarjeta de crédito' => 'ccsave', 'PayPal' => 'paypal_express', 'Transferencia bancaria' => 'banktransfer', 'Contra reembolso' => 'cashondelivery'];
            $selectedPayments = $helper->ask($input, $output, (new ChoiceQuestion('Formas de pago a habilitar:', array_keys($paymentMethods), 0))->setMultiselect(true));
            $config['payment_methods'] = array_intersect_key($paymentMethods, array_flip($selectedPayments));

            // Formas de envío
            $shippingMethods = ['Envío estándar' => 'flatrate', 'Envío express' => 'tablerate', 'Recogida en tienda' => 'freeshipping'];
            $selectedShipping = $helper->ask($input, $output, (new ChoiceQuestion('Formas de envío a habilitar:', array_keys($shippingMethods), 0))->setMultiselect(true));
            $config['shipping_methods'] = array_intersect_key($shippingMethods, array_flip($selectedShipping));

            // Almacenes
            $numWarehouses = (int)$helper->ask($input, $output, new Question('Número de almacenes (1): ', 1));
            $config['warehouses'] = [];
            for ($i = 1; $i <= $numWarehouses; $i++) {
                $warehouse = [];
                $warehouse['name'] = $helper->ask($input, $output, new Question("Nombre del almacén #{$i}: ", 'Almacén #{$i}'));
                $warehouse['address'] = $helper->ask($input, $output, new Question("Dirección del almacén #{$i}: ", 'Dirección almacén #{$i}'));
                $warehouse['phone'] = $helper->ask($input, $output, new Question("Teléfono del almacén #{$i}: ", "{$i}99 - 222 - 333"));
                $config['warehouses'][] = $warehouse;
            }

            // Países de envío
            $shippingCountriesOptions = [
                'ES' => 'España',
                'EU' => 'Todos los países de la UE',
                'ALL' => 'Todo el mundo'
            ];
            $config['shipping_countries'] = $helper->ask($input, $output, new ChoiceQuestion(
                '¿A qué países deseas enviar?',
                array_values($shippingCountriesOptions),
                0
            ));

            // País de origen
            $originCountryOptions = [
                'ES' => 'España',
                'FR' => 'Francia',
                'GB' => 'Reino Unido',
                'IT' => 'Italia',
                'PT' => 'Portugal'
            ];
            $selectedOriginCountry = $helper->ask($input, $output, new ChoiceQuestion(
                'Selecciona el país de origen de la tienda:',
                array_values($originCountryOptions),
                0
            ));
            $config['origin_country'] = array_search($selectedOriginCountry, $originCountryOptions);

            // Número de productos por página (cuadrícula)
            $config['grid_per_page'] = (int)$helper->ask($input, $output, new Question(
                'Número de productos por página en la vista cuadrícula (12): ',
                12
            ));

            // Número de productos por página (cuadrícula/lista)
            $config['grid_list_per_page'] = (int)$helper->ask($input, $output, new Question(
                'Número de productos por página en vista cuadrícula/lista (12,24,36): ',
                '12,24,36'
            ));

            // Requiere confirmación por correo electrónico
            $config['email_confirmation'] = $helper->ask($input, $output, new ConfirmationQuestion(
                '¿Requerir confirmación por correo electrónico para nuevas cuentas? [y/N]: ',
                false
            ));

            // Clientes online
            $config['section_data_lifetime'] = $helper->ask($input, $output, new Question('Tiempo en minutos datos clientes online (60): ', '60'));

            file_put_contents(BP . '/var/store_config.json', json_encode($config, JSON_PRETTY_PRINT));
            $output->writeln('<info>Configuración guardada en var/store_config.json</info>');
        }  

        if ($helper->ask($input, $output, new ConfirmationQuestion('¿Aplicar configuración ahora? [y/N]: ', false))) {
            $this->grabaCambio($config, $output);
            $output->writeln('<info>Configuración aplicada correctamente.</info>');
        } 

        return Command::SUCCESS;
    }

    private function grabaCambio(array $config, OutputInterface $output) {

        $output->writeln('<info>Entra en aplicar la información.</info>');

        // Motor de búsqueda
        // $this->configWriter->save('catalog/search/engine', $config['search_engine']);
        // $this->configWriter->save('catalog/search/opensearch_server_hostname', $config['opensearch_host']);
        // $this->configWriter->save('catalog/search/opensearch_server_port', $config['opensearch_port']);

        // Categoría raíz
        // $this->configWriter->save('catalog/category/root_id', $config['root_category_id']);

        // URLs SEO
        // $this->configWriter->save('web/seo/use_rewrites', $config['use_rewrites'] ? '1' : '0');
        // $this->configWriter->save('web/unsecure/base_url', $config['base_url']);
        // $this->configWriter->save('web/secure/base_url', $config['base_url']);

        // Moneda
        $this->configWriter->save('currency/options/base', $config['currency_base']);
        $this->configWriter->save('currency/options/default', $config['currency_base']);
        $this->configWriter->save('currency/options/allow', implode(',', $config['currency_allowed']));
        
        // Localización
        $output->writeln('<info>Localización</info>');
        $this->configWriter->save('general/locale/code', $config['locale']);
        $this->configWriter->save('general/locale/timezone', $config['timezone']);
        $this->configWriter->save('general/locale/weight_unit', $config['weight_unit']);
        $this->configWriter->save('general/locale/firstday', $config['firstday']);

        // País predeterminado
        $this->configWriter->save('general/country/default', $config['default_country']);
        $this->configWriter->save('general/country/allow', $config['default_country']);

        // Información tienda
        $output->writeln('<info>Información tienda</info>');
        $this->configWriter->save('general/store_information/name', $config['store_name']);
        $this->configWriter->save('general/store_information/hours', $config['store_hours']);
        $this->configWriter->save('general/store_information/phone', $config['store_phone']);
        $this->configWriter->save('general/store_information/country_id', $config['store_country_id']);
        $this->configWriter->save('general/store_information/region_id', $config['store_region_id']);
        $this->configWriter->save('general/store_information/postcode', $config['store_postcode']);
        $this->configWriter->save('general/store_information/city', $config['store_city']);
        $this->configWriter->save('general/store_information/street_line1', $config['store_street1']);
        $this->configWriter->save('general/store_information/street_line2', $config['store_street2']);
        $this->configWriter->save('general/store_information/merchant_vat_number', $config['merchant_vat']);

        
        // Email confirmación nuevas cuentas
        $this->configWriter->save('customer/create_account/confirm', $config['email_confirmation'] ? '1' : '0');

        // Analytics
        // $this->configWriter->save('analytics/subscription/enabled', $config['analytics_enabled'] ? '1' : '0');
        // $this->configWriter->save('crontab/default/jobs/analytics_subscribe/schedule/cron_expr', '0 * * * *');
        // $this->configWriter->save('crontab/default/jobs/analytics_collect_data/schedule/cron_expr', '00 02 * * *');

        // Clientes online
        $this->configWriter->save('customer/online_customers/section_data_lifetime', $config['section_data_lifetime']);
        
        // CAPTCHA (si no se usa)
        if (in_array('No usar', $config['captcha'])) {
            $this->configWriter->save('customer/captcha/enable', 0);
            $this->configWriter->save('customer/captcha/forms', '');
        }
        
        // Pago
        foreach ($config['payment_methods'] as $label => $code) {
            $this->configWriter->save("payment/{$code}/active", 1);
        }
        
        // Formas de envío
        foreach ($config['shipping_methods'] as $label => $code) {
            $this->configWriter->save("carriers/{$code}/active", 1);
        }

        $this->cacheTypeList->cleanType('config');
        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }

        // Email transaccional
        $this->configWriter->save('trans_email/ident_general/email', $config['trans_email']);

        // CAPTCHA
        if (!in_array('No usar', $config['captcha'])) {
            $this->configWriter->save('customer/captcha/enable', 1);
            $captchaForms = implode(',', $config['captcha']);
            $this->configWriter->save('customer/captcha/forms', $captchaForms);
        } else {
            $this->configWriter->save('customer/captcha/enable', 0);
        }

        // 2FA
        $this->configWriter->save('twofactorauth/general/enable', $config['2fa'] ? 1 : 0);

        // Configuración de moneda e idioma por store view
        foreach ($config['websites'] as $website) {
            foreach ($website['store_views'] as $storeView) {
                $storeCode = strtolower(str_replace(' ', '_', $storeView['name']));
                $this->configWriter->save('general/locale/code', strtolower($storeView['language']), 'stores', $storeCode);
                $this->configWriter->save('currency/options/default', $storeView['currency'], 'stores', $storeCode);
                $this->configWriter->save('currency/options/allow', $storeView['currency'], 'stores', $storeCode);
            }
        }

        // Analytics
        // $this->configWriter->save('analytics/subscription/enabled', $config['analytics_enabled'] ? '1' : '0');
        // $this->configWriter->save('crontab/default/jobs/analytics_subscribe/schedule/cron_expr', '0 * * * *');
        // $this->configWriter->save('crontab/default/jobs/analytics_collect_data/schedule/cron_expr', '00 02 * * *');

        // Almacenes (Inventory Source)
        foreach ($config['warehouses'] as $warehouse) {
            // Esto requiere MSI instalado (InventoryApi y SourceRepository)
            /**$source = $this->sourceFactory->create();
            $source->setSourceCode(strtoupper(str_replace(' ', '_', $warehouse['name'])));
            $source->setName($warehouse['name']);
            $source->setContactPhone($warehouse['phone']);
            $source->setStreet($warehouse['address']);
            $source->setEnabled(true);
            $this->sourceRepository->save($source);
            **/
        }

        // Países de envío permitidos
        switch ($config['shipping_countries']) {
            case 'España':
                $this->configWriter->save('general/country/allow', 'ES');
                break;
            case 'Todos los países de la UE':
                $euCountries = 'AT,BE,BG,HR,CY,CZ,DK,EE,FI,FR,DE,GR,HU,IE,IT,LV,LT,LU,MT,NL,PL,PT,RO,SK,SI,ES,SE';
                $this->configWriter->save('general/country/allow', $euCountries);
                break;
            default: // Todo el mundo
                $this->configWriter->save('general/country/allow', '');
                break;
        }

        // País de origen
        $this->configWriter->save('shipping/origin/country_id', $config['origin_country']);

        // Número de productos por página (cuadrícula)
        $this->configWriter->save('catalog/frontend/grid_per_page', $config['grid_per_page']);

        // Número de productos por página disponibles (cuadrícula/lista)
        $this->configWriter->save('catalog/frontend/grid_per_page_values', $config['grid_list_per_page']);
        $this->configWriter->save('catalog/frontend/list_per_page', $config['grid_per_page']);
        $this->configWriter->save('catalog/frontend/list_per_page_values', $config['grid_list_per_page']);

        // Finalmente, limpiar caché tras aplicar cambios
        $this->cacheTypeList->cleanType('config');
        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
        
        return;
    }   

}

/**
 * bin/magento config:show
 * catalog/search/engine - opensearch
 * catalog/search/opensearch_server_hostname - opensearch
 * catalog/search/opensearch_server_port - 9200
 * catalog/category/root_id - 2
 * web/seo/use_rewrites - 1
 * web/unsecure/base_url - https://magento.test/
 * web/secure/base_url - https://magento.test/
 * general/locale/code - es_ES
 * general/locale/timezone - Europe/Madrid
 * general/locale/weight_unit - kgs
 * general/locale/firstday - 1
 * general/region/display_all - 1
 * general/region/state_required - AL,AR,AU,BY,BO,BR,BG,CA,CL,CN,CO,CR,HR,CZ,DK,EC,EE,GR,GY,IS,IN,IT,LV,LT,MX,PY,PE,PL,PT,RO,ES,SR,SE,CH,UA,US,UY,VE
 * general/country/default - ES
 * general/country/allow - ES
 * general/country/destinations - 
 * eneral/store_information/name - Atelier Alzuet
 * general/store_information/phone - 
 * general/store_information/hours - 
 * general/store_information/country_id - ES
 * general/store_information/region_id - 181
 * general/store_information/postcode - 50012
 * general/store_information/city - Zaragoza
 * general/store_information/street_line1 - 
 * general/store_information/street_line2 - 
 * general/store_information/merchant_vat_number - 
 * general/single_store_mode/enabled - 0
 * currency/options/base - EUR
 * currency/options/default - EUR
 * currency/options/allow - EUR
 * currency/fixerio/api_key - 
 * currency/fixerio/timeout - 100
 * currency/fixerio_apilayer/api_key - 
 * currency/fixerio_apilayer/timeout - 100
 * currency/currencyconverterapi/api_key - 
 * currency/currencyconverterapi/timeout - 100
 * twofactorauth/duo/application_key - hn4qOdE4590kN3f4LT2lwusD3dNjFbQaa1UGJ0mAGMeyzNHYjUcQ1dLZsBrwU8vR
 * analytics/subscription/enabled - 1
 * crontab/default/jobs/analytics_subscribe/schedule/cron_expr - 0 * * * *
 * crontab/default/jobs/analytics_collect_data/schedule/cron_expr - 00 02 * * *
 * msp_securitysuite_recaptcha/frontend/enabled - 0
 * msp_securitysuite_recaptcha/backend/enabled - 0
 * admin/usage/enabled - 0
 * atelier_email/general/enabled - 1
 * atelier_email/general/api_key - ******
 * atelier_email/general/test_mode - 1
 * atelier_email/general/test_email - alzuet@gmail.com
 * customer/online_customers/online_minutes_interval - 
 * customer/online_customers/section_data_lifetime - 60
 * customer/captcha/enable - 0
 * customer/captcha/forms - 
 */