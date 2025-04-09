<?php

declare(strict_types=1);

namespace Atelier\MosSetup\Model;

use Atelier\MosSetup\Logger\CustomLogger;
use Atelier\MosSetup\Helper\SecureContextExecutor;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Customer\Api\Data\GroupInterfaceFactory as CustomerGroupFactory;

class CustomerManager
{
    private const CUSTOMER_COUNT = 50;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly AddressInterfaceFactory $addressFactory,
        private readonly RegionInterfaceFactory $regionFactory,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly CustomerGroupFactory $customerGroupFactory,
        private readonly SecureContextExecutor $secureContextExecutor,
        private readonly CustomLogger $logger
    ) {}
    
    /**
     * Provincias aleatorias
     */
    private array $provinces = ['Madrid', 'Salamanca', 'Murcia', 'Sevilla', 
    'Toledo', 'Zaragoza','Santa Cruz de Tenerife'];

    /**
     * Escritores en español
     */
    private array $spanishWriters = [
        'Miguel de Cervantes',
        'Federico García Lorca',
        'Antonio Machado',
        'Camilo José Cela',
        'Miguel Delibes',
        'Ana María Matute',
        'Benito Pérez Galdós',
        'Rosalía de Castro',
        'Ramón del Valle-Inclán',
        'Mario Vargas Llosa',
        'Vicente Aleixandre',
        'Juan Ramón Jiménez',
        'Rosa Montero'
    ];

    /**
     * Male first names
     */
    private array $maleFirstNames = [
        'Alejandro', 'Carlos', 'Máximo', 'Javier', 'Juan', 
        'Luis', 'Manuel', 'Jorge', 'Pablo', 'Sergio', 'Francisco', 'Álex', 'Jaime', 'Guillermo'
    ];

    /**
     * Female first names
     */
    private array $femaleFirstNames = [
        'Ana', 'Carmen', 'Elena', 'Nuria', 'Laura', 
        'María', 'Patricia', 'Pilar', 'Sofía', 'Victoria', 'Blanca', 'Aurora'
    ];

    /**
     * Last names
     */
    private array $lastNames = [
        'García', 'González', 'Rodríguez', 'Fernández', 'López', 
        'Martínez', 'Sánchez', 'Pérez', 'Gómez', 'Martín', 
        'Jiménez', 'Ruiz', 'Hernández', 'Díaz', 'Moreno', 
        'Sabba', 'Muñoz', 'Romero', 'Alonso', 'Gutiérrez',
        'Llaugí', 'Alzuet', 'Gándara', 'Lázaro'
    ];

    /**
     * Clean all customers except admin
     */
    public function cleanCustomers(): void
    {
        $this->secureContextExecutor->execute(function (): void {
            try {
                $customerCollection = $this->customerCollectionFactory->create();
                $customerCollection->addAttributeToFilter('email', ['neq' => 'alzuet@gmail.com']);
                foreach ($customerCollection as $customer) {
                    try {
                        // Convert the Customer model to CustomerInterface
                        $customerData = $this->customerRepository->getById($customer->getId());
                        $this->customerRepository->delete($customerData);
                    } catch (\Exception $e) {
                        $this->logger->error('[CustomerManager] Error al borrar cliente con ID ' . $customer->getId() . ': ' . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('[CustomerManager] Error al borrar clientes: ' . $e->getMessage());
            }
        });
    }

    /**
     * Create customers with addresses
     */
    public function createCustomers(): void
    {
        $this->secureContextExecutor->execute(function (): void {
            
            $customerGroups = $this->getOrCreateCustomerGroups();
            
            for ($i = 0; $i < self::CUSTOMER_COUNT; $i++) {
                try {
                    $isMale = $i < (self::CUSTOMER_COUNT / 2);
                    $firstName = $this->getRandomName($isMale);
                    $lastName = $this->getRandomLastName();

                    // Eliminar acentos y caracteres especiales
                    $normalizedFirstName = transliterator_transliterate('Any-Latin; Latin-ASCII; [^a-zA-Z0-9] remove', $firstName);
                    $normalizedLastName = transliterator_transliterate('Any-Latin; Latin-ASCII; [^a-zA-Z0-9] remove', $lastName);

                    // Convertir a minúsculas y generar el email
                    $email = strtolower($normalizedFirstName . '.' . $normalizedLastName . '@mitienda.com');
                    
                    // Select a random customer group
                    $randomGroup = $customerGroups[array_rand($customerGroups)];    

                    // Create customer
                    $customer = $this->customerFactory->create();
                    $customer->setFirstname($firstName)
                             ->setLastname($lastName)
                             ->setEmail($email)
                             ->setGender($isMale ? 1 : 2) // 1 = Male, 2 = Female
                             ->setGroupId($randomGroup->getId())
                             ->setWebsiteId($this->storeManager->getWebsite()->getId())
                             ->setStoreId($this->storeManager->getStore()->getId())
                             ->setConfirmation(null)
                             ->setCreatedIn($this->storeManager->getStore()->getName())
                             ->setCustomAttribute('customer_gender', $isMale ? 1 : 2)
                             ->setCustomAttribute('allow_remote_assistance', 1);
                    
                    // Save customer to get ID
                    $savedCustomer = $this->customerRepository->save($customer);
                    
                    // Create billing address
                    $billingAddress = $this->createAddress(
                        (int) $savedCustomer->getId(),
                        $firstName,
                        $lastName,
                        true,
                        false
                    );
                    
                    // Create two shipping addresses
                    $shippingAddress1 = $this->createAddress(
                        (int) $savedCustomer->getId(),
                        $firstName,
                        $lastName,
                        false,
                        true
                    );
                    
                    $shippingAddress2 = $this->createAddress(
                        (int) $savedCustomer->getId(),
                        $firstName,
                        $lastName,
                        false,
                        false
                    );
                    
                    // Set addresses to customer
                    $savedCustomer->setAddresses([
                        $billingAddress,
                        $shippingAddress1,
                        $shippingAddress2
                    ]);

                    // Save customer with addresses
                    $savedCustomer = $this->customerRepository->save($savedCustomer);

                    $this->logger->info('[CustomerManager] Se crea cliente', [
                        'customer_id' => $savedCustomer->getId(),
                        'shipping_id' => $savedCustomer->getDefaultShipping(),
                        'billing_id' => $savedCustomer->getDefaultBilling()
                    ]);
                    
                } catch (\Exception $e) {
                    $this->logger->error("[CustomerManager] Error al crear cliente {$i}: " . $e->getMessage());
                }
            }
        });
    }

    /**
     * Create an address for a customer
     */
    private function createAddress(
        int $customerId,
        string $firstName,
        string $lastName,
        bool $isDefaultBilling,
        bool $isDefaultShipping
    ): AddressInterface {
        $province = $this->getRandomProvince();
        $street = $this->getRandomStreet();
        $streetNumber = $this->getRandomInt(1, 150);
        $postCode = $this->getRandomPostCode($province);

        $region = $this->regionFactory->create();
        $region->setRegion($province);
        $region->setRegionId($this->getRegionIdByName($province));
        $region->setRegionCode($this->getRegionCodeByName($province));
        
        $address = $this->addressFactory->create();
        $address->setCustomerId($customerId)
                ->setFirstname($firstName)
                ->setLastname($lastName)
                ->setCountryId('ES')
                ->setRegion($region)
                ->setRegionId($this->getRegionIdByName($province))
                ->setPostcode($postCode)
                ->setCity($this->getCityByProvince($province))
                ->setStreet([$street . ', ' . $streetNumber])
                ->setTelephone($this->getRandomPhone())
                ->setIsDefaultBilling($isDefaultBilling)
                ->setIsDefaultShipping($isDefaultShipping);
        
        return $address;
    }

    /**
     * Get random male or female name
     */
    private function getRandomName(bool $isMale): string
    {
        $names = $isMale ? $this->maleFirstNames : $this->femaleFirstNames;
        return $names[array_rand($names)];
    }

    /**
     * Get random last name
     */
    private function getRandomLastName(): string
    {
        return $this->lastNames[array_rand($this->lastNames)];
    }

    /**
     * Get random province
     */
    private function getRandomProvince(): string
    {
        return $this->provinces[array_rand($this->provinces)];
    }

    /**
     * Get random writer street name
     */
    private function getRandomStreet(): string
    {
        $writer = $this->spanishWriters[array_rand($this->spanishWriters)];
        return 'Calle ' . $writer;
    }

    /**
     * Get post code by province
     */
    private function getRandomPostCode(string $province): string
    {
        $prefixes = [
            'Madrid' => '28',
            'Salamanca' => '37',
            'Murcia' => '30',
            'Sevilla' => '41',
            'Toledo' => '45',
            'Santa Cruz de Tenerife' => '38',
            'Zaragoza' => '50'
        ];
        
        $prefix = $prefixes[$province] ?? '28'; // Default to Madrid if province not found
        return $prefix . str_pad((string)$this->getRandomInt(1, 999), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get a city name by province
     */
    private function getCityByProvince(string $province): string
    {
        $cities = [
            'Madrid' => ['Madrid', 'Alcalá de Henares', 'Móstoles', 'Getafe', 'Alcorcón'],
            'Salamanca' => ['Salamanca', 'Béjar', 'Ciudad Rodrigo', 'Alba de Tormes', 'Peñaranda de Bracamonte'],
            'Orense' => ['Orense', 'Verín', 'Carballino', 'Ribadavia', 'Allariz'],
            'Sevilla' => ['Sevilla', 'Dos Hermanas', 'Écija', 'Utrera', 'Alcalá de Guadaíra'],
            'Toledo' => ['Toledo', 'Talavera de la Reina', 'Illescas', 'Consuegra', 'Ocaña'],
            'Zaragoza' => ['Zaragoza', 'Egea de los Caballeros', 'Sádaba'],
            'Santa Cruz de Tenerife'  => ['Adeje', 'Tenerife', 'La Laguna'],
        ];
        
        $provinceCities = $cities[$province] ?? ['Ciudad Desconocida'];
        return $provinceCities[array_rand($provinceCities)];
    }

    /**
     * Get random phone number
     */
    private function getRandomPhone(): string
    {
        // Spanish mobile prefix
        $prefixes = ['6', '7'];
        $prefix = $prefixes[array_rand($prefixes)];
        
        // Generate 8 more digits
        $number = '';
        for ($i = 0; $i < 8; $i++) {
            $number .= (string)$this->getRandomInt(0, 9);
        }
        
        return $prefix . $number;
    }

    /**
     * Get region ID by province name
     */
    private function getRegionIdByName(string $provinceName): int
    {
        // Map of province names to region IDs in Magento's directory_country_region table
        $regionMap = [
            'Madrid' => 161,
            'Salamanca' => 169,
            'Murcia' => 164,
            'Sevilla' => 172,
            'Toledo' => 176,
            'Santa Cruz de Tenerife' => 170,
            'Zaragoza' => 181
        ];
        
        return $regionMap[$provinceName] ?? 181; // Defecto Zaragoza
    }

    /**
     * Get region Code by province name
     */
    private function getRegionCodeByName(string $provinceName): string
    {
        // Map of province names to region codes in Magento's directory_country_region table for Spain
        $regionMap = [
            'Madrid' => 'Madrid',
            'Salamanca' => 'Salamanca',
            'Orense' => 'Orense',
            'Sevilla' => 'Sevilla',
            'Toledo' => 'Toledo',
            'Santa Cruz de Tenerife' => 'Santa Cruz de Tenerife'
        ];
        
        return $regionMap[$provinceName] ?? 'MD'; // Default to Madrid if not found
    }

    /**
     * Get random integer
     */
    private function getRandomInt(int $min, int $max): int
    {
        try {
            return random_int($min, $max);
        } catch (\Exception $e) {
            // Fallback if random_int fails
            return mt_rand($min, $max);
        }
    }

    /**
     * Obtiene o crea grupos adicionales en Magento
     * 
     * @return array
     */
    private function getOrCreateCustomerGroups()
    {
        // Search for existing groups
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $groupList = $this->groupRepository->getList($searchCriteria);

        // Comporobar los que tenemos, no queremos not logged in ni general
        $groups = array_filter($groupList->getItems(), function($group) {
            return !in_array($group->getId(), [0, 1]);
        });
        
        if (count($groups) < 7) {
            // Hay 4 por defecto, quiero añadir estos tres
            $groupNames = ['Cobre', 'Plata', 'Oro'];
            $createdGroups = [];

            foreach ($groupNames as $groupName) {
                // Check if group already exists to avoid duplicates
                $existingGroup = null;
                foreach ($groups as $group) {
                    if (strtolower($group->getCode()) === strtolower($groupName)) {
                        $existingGroup = $group;
                        break;
                    }
                }

                if (!$existingGroup) {
                    try {
                        // Create a new group using the GroupInterface
                        $newGroup = $this->customerGroupFactory->create()
                            ->setCode($groupName)
                            ->setTaxClassId(3); // Default tax class, adjust if needed
    
                        // Save the group
                        $savedGroup = $this->groupRepository->save($newGroup);
                        $createdGroups[] = $savedGroup;
                    } catch (\Exception $e) {
                        $this->logger->error('[CustomerManager] Error al crear grupo de cliente: ' . $e->getMessage());
                    }
                } else {
                    $createdGroups[] = $existingGroup;
                }
            }

            return $createdGroups;
        }

        return $groups;
    }

}