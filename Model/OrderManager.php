<?php

declare(strict_types=1);

namespace Atelier\MosSetup\Model;

use Atelier\MosSetup\Logger\CustomLogger;
use Atelier\MosSetup\Helper\SecureContextExecutor;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\OrderService;
use Magento\Store\Model\StoreManagerInterface;

class OrderManager
{
    private const ORDER_COUNT = 10;
    private const MIN_PRODUCTS_PER_ORDER = 1;
    private const MAX_PRODUCTS_PER_ORDER = 5;
    private const MAX_QUANTITY_PER_PRODUCT = 3;

    /**
     * Status de pedido
     */
    private array $orderStatuses = [
        Order::STATE_NEW,
        Order::STATE_PROCESSING,
        Order::STATE_COMPLETE,
        Order::STATE_CLOSED,
        Order::STATE_CANCELED,
        Order::STATE_HOLDED
    ];

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly QuoteFactory $quoteFactory,
        private readonly QuoteManagement $quoteManagement,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly PaymentHelper $paymentHelper,
        private readonly OrderService $orderService,
        private readonly SecureContextExecutor $secureContextExecutor,
        private readonly CustomLogger $logger,
        private readonly OrderRepositoryInterface $orderRepository
    ) {}

    /**
     * Clean all orders
     */
    public function cleanOrders(): void
    {
        $this->secureContextExecutor->execute(function (): void {
            try {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $orderCollection = $objectManager->create(\Magento\Sales\Model\ResourceModel\Order\Collection::class);
                
                foreach ($orderCollection as $order) {
                    try {
                        $order->delete();
                    } catch (\Exception $e) {
                        $this->logger->error('[OrderManager] Error al borrar el pedido ID ' . $order->getId() . ': ' . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('[OrderManager] Error al borrar los pedidos: ' . $e->getMessage());
            }
        });
    }

    /**
     * Create orders
     */
    public function createOrders(): void
    {
        $this->secureContextExecutor->execute(function (): void {
            $customers = $this->getRandomCustomers();
            $products = $this->getAvailableProducts();

            $this->logger->error("[OrderManager] Clientes disponibles: " . count($customers));
            $this->logger->error("[OrderManager] Productos disponibles: " . count($products));

            if (empty($customers) || empty($products)) {
                $this->logger->error('No customers or products found to create orders');
                return;
            }

            $storeId = $this->storeManager->getStore()->getId();
            // $websiteId = $this->storeManager->getStore()->getWebsiteId();
            $paymentMethod = $this->getDefaultPaymentMethod();

            for ($i = 0; $i < self::ORDER_COUNT; $i++) {
                try {
                    // Select a random customer
                    $customerId = $customers[array_rand($customers)];
                    $customer = $this->customerRepository->getById($customerId);

                    // Create a new quote
                    $quote = $this->quoteFactory->create();
                    $quote->setStoreId($storeId);
                    $quote->setIsActive(true);
                    $quote->setIsMultiShipping(false);
                    $quote->assignCustomer($customer);
                    
                    // Get the first shipping address (if available)
                    $shippingAddressId = null;
                    $customerAddresses = $customer->getAddresses();
                    foreach ($customerAddresses as $address) {
                        if ($address->isDefaultShipping()) {
                            $shippingAddressId = $address->getId();
                            break;
                        }
                    }
                    
                    // If no default shipping address, get the first one
                    if (!$shippingAddressId && !empty($customerAddresses)) {
                        $shippingAddressId = $customerAddresses[0]->getId();
                    }
                    
                    if (!$shippingAddressId) {
                        $this->logger->error("[OrderManager] El cliente {$customerId} no tiene dirección de envío. No se creará el pedido.");
                        continue;
                    }
                    
                    // Add random products to quote
                    $numProducts = $this->getRandomInt(
                        self::MIN_PRODUCTS_PER_ORDER, 
                        min(self::MAX_PRODUCTS_PER_ORDER, count($products))
                    );
                    
                    $randomProducts = array_rand($products, $numProducts);
                    // Convert to array if only one product selected
                    if (!is_array($randomProducts)) {
                        $randomProducts = [$randomProducts];
                    }
                    
                    $orderTotal = 0;
                    foreach ($randomProducts as $productIndex) {
                        $productId = $products[$productIndex];
                        
                        try {
                            $product = $this->productRepository->getById($productId);
                            
                            // Skip if product is not available
                            /** @var \Magento\Catalog\Model\Product $product */
                            if (!$product->isAvailable() || !$product->isSaleable()) {
                                $this->logger->error("[OrderManager] Producto no disponible o no vendible, saltando...");
                                continue;
                            }
                            
                            $quantity = $this->getRandomInt(1, self::MAX_QUANTITY_PER_PRODUCT);
                            
                            // El problema puede estar en cómo se agregan los productos
                            // Modifica la forma de agregar productos para asegurarte que funciona:
                            $params = new \Magento\Framework\DataObject([
                                'product' => $productId,
                                'qty' => $quantity
                            ]);
                            
                            $quote->addProduct($product, $params);
                            $orderTotal += $product->getFinalPrice() * $quantity;
                            // $this->logger->error("✓ Producto agregado correctamente a la cotización");
                            
                        } catch (\Exception $e) {
                            $this->logger->error("[OrderManager] Error al agregar producto {$productId}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                        }
                    }

                    // Después del bucle de productos
                    $items = $quote->getAllItems();
                    if (count($items) == 0) {
                        $this->logger->error("[OrderManager] No se pudieron agregar productos a la cotización. Abortando creación de pedido.");
                        continue;
                    }
                    
                    // Skip if no products could be added
                    if ($orderTotal == 0) {
                        $this->logger->error("[OrderManager] No hay productos válidos para añadir al pedido. Skipping.");
                        continue;
                    }
                    
                    // Set addresses
                    $shippingAddress = $quote->getShippingAddress();
                    
                    // Set shipping method
                    $shippingAddress->setCollectShippingRates(true)
                                    ->collectShippingRates()
                                    ->setShippingMethod('flatrate_flatrate');
                    
                    // Set payment method
                    $quote->setPaymentMethod($paymentMethod);
                    $quote->save();

                    // Set payment data
                    $quote->getPayment()->importData(['method' => $paymentMethod]);
                    
                    // Collect totals and save quote
                    $quote->collectTotals();
                    $this->cartRepository->save($quote);

                    // Create order from quote
                    $orderId = $this->cartManagement->placeOrder($quote->getId());
                    
                    if ($orderId) {
                        // Load the complete order object using the ID
                        $order = $this->orderRepository->get($orderId);
                        
                        // Now you can set the state on the order object
                        $randomStatus = $this->orderStatuses[array_rand($this->orderStatuses)];
                        $order->setState($randomStatus)->setStatus($randomStatus);
                        
                        // Set random created_at date (within the last 6 months)
                        $daysAgo = $this->getRandomInt(0, 180);
                        $createdAt = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));
                        $order->setCreatedAt($createdAt);
                        
                        // Save the order
                        $savedOrder = $this->orderRepository->save($order);
                        $this->logger->info('[OrderManager] Se crea cliente', [
                            'order_id' => $savedOrder->getIncrementId(),
                            'customer' => $savedOrder->getCustomerLastname(),
                            'status' => $savedOrder->getStatus(),
                        ]);
                        
                        echo "[OrderManager] Pedido creado #{$order->getIncrementId()} para cliente {$customer->getFirstname()} {$customer->getLastname()} with status {$randomStatus}" . PHP_EOL;
                    }
                    
                } catch (\Exception $e) {
                    $this->logger->error("[OrderManager] Error al crear pedido {$i}: " . $e->getMessage());
                }
            }
        });
    }

    /**
     * Get random customers
     */
    private function getRandomCustomers(): array
    {
        $customerIds = [];
        try {
            $customerCollection = $this->customerCollectionFactory->create();
            foreach ($customerCollection as $customer) {
                $customerIds[] = $customer->getId();
            }
        } catch (\Exception $e) {
            $this->logger->error('[OrderManager] Error al obtener clientes: ' . $e->getMessage());
        }
        
        return $customerIds;
    }

    /**
     * Get available products
     */
    private function getAvailableProducts(): array
    {
        $productIds = [];
        try {
            $productCollection = $this->productCollectionFactory->create();
            $productCollection->addAttributeToSelect('*')
                ->addAttributeToFilter('type_id', ['in' => ['simple', 'virtual']])
                ->addAttributeToFilter('status', 1) // Only enabled products
                ->addAttributeToFilter('visibility', ['neq' => 1]) // Not Not Visible Individually
                ->setPageSize(100);
            
            foreach ($productCollection as $product) {
                if ($product->isSalable()) {
                    $productIds[] = $product->getId();
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('[OrderManager] Error al obtener productos: ' . $e->getMessage());
        }
        
        return $productIds;
    }

    /**
     * Get default payment method
     */
    private function getDefaultPaymentMethod(): string
    {
        try {
            $paymentMethods = $this->paymentHelper->getPaymentMethods();
            $availableMethods = array_keys($paymentMethods);
            
            // Preferimos ciertos métodos de pago si están disponibles
            $preferredMethods = ['checkmo', 'banktransfer', 'cashondelivery'];
            
            foreach ($preferredMethods as $method) {
                if (in_array($method, $availableMethods)) {
                    return $method;
                }
            }
            
            // Si no encontramos ninguno de los preferidos, usar el primero disponible
            if (!empty($availableMethods)) {
                return $availableMethods[0];
            }
        } catch (\Exception $e) {
            $this->logger->error('[OrderManager] Error al obtener formas de pago: ' . $e->getMessage());
        }
        
        // Si todo falla, devolver checkmo (pago con cheque)
        return 'checkmo';
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
}