<?php
namespace Werules\RestApi\Model;

use Magento\Framework\DataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Werules\RestApi\Api\CartManagementInterface;
use Magento\Checkout\Model\Cart;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\Config\ScopeConfigInterface;

class CartManagement implements CartManagementInterface
{
    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /** @var LoggerInterface */
    private $logger;

    /**
     * Constructor
     *
     * @param Cart $cart
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        Cart $cart,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        CartRepositoryInterface $cartRepository,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
    ) {
        $this->cart = $cart;
        $this->productRepository = $productRepository;
        $this->cartRepository = $cartRepository;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * Check if the Game Shop module is enabled.
     *
     * @return bool
     */
    private function isModuleEnabled()
    {
        return $this->scopeConfig->isSetFlag('restapi/general/enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return the current cart item count for this session/user.
     *
     * @return int
     */
    public function getCartItemCount()
    {
        if (!$this->isModuleEnabled()) {
            return 0;
        }

        $quote = $this->cart->getQuote();
        return (int)$quote->getItemsQty();
    }

    /**
     * Add product to cart.
     *
     * @param int $productId
     * @param int|float $qty
     * @param string|null $couponCode
     * @return array
     */
    public function addItemToCart($productId, $qty = 1, $couponCode = null)
    {
        if (!$this->isModuleEnabled()) {
            return ['success' => false, 'message' => 'RestApi is disabled.', 'cart_count' => 0];
        }

        try {
            $storeId = $this->storeManager->getStore()->getId();
            $product = $this->productRepository->getById($productId, false, $storeId);

            $requestInfo = new DataObject(['qty' => $qty]);

            $quote = $this->cart->getQuote();
            $quote->addProduct($product, $requestInfo);

            $quote->collectTotals()
                ->setTotalsCollectedFlag(false)
                ->collectTotals();

            $this->cartRepository->save($quote);

            if ($couponCode) {
                $quote->setCouponCode($couponCode)
                    ->collectTotals()
                    ->save();
            }

            return [
                'success' => true,
                'message' => sprintf('Added "%s" to cart.', $product->getName()),
                'cart_count' => (int) $quote->getItemsQty(),
            ];
        } catch (NoSuchEntityException $e) {
            return [
                'success' => false,
                'message' => 'Product not found.',
                'cart_count' => $this->getCartItemCount(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Could not add product to cart: ' . $e->getMessage(),
                'cart_count' => $this->getCartItemCount(),
            ];
        }
    }
}
