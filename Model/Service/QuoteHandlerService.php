<?php

namespace CheckoutCom\Magento2\Model\Service;

use Magento\Customer\Api\Data\GroupInterface;

class QuoteHandlerService
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Product
     */
    protected $productModel;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ShopperHandlerService
     */
    protected $shopperHandler;

    /**
     * QuoteHandlerService constructor
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Product $productModel,
        \CheckoutCom\Magento2\Gateway\Config\Config $config,
        \CheckoutCom\Magento2\Model\Service\ShopperHandlerService $shopperHandler
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->cookieManager = $cookieManager;
        $this->quoteFactory = $quoteFactory;
        $this->storeManager = $storeManager;
        $this->productModel = $productModel;
        $this->config = $config;
        $this->shopperHandler = $shopperHandler;
    }

    /**
     * Find a quote
     */
    public function getQuote($fields = []) {
        try {
            if (count($fields) > 0) {
                // Get the quote factory
                $quoteFactory = $this->quoteFactory
                    ->create()
                    ->getCollection();

                // Add search filters
                foreach ($fields as $key => $value) {
                    $quoteFactory->addFieldToFilter(
                        $key,
                        $value
                    );
                }

                // Return the first result found
                return $quoteFactory->getFirstItem();
            }
            else {
                // Try to find the quote in session
                return $this->checkoutSession->getQuote();
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a new quote
     */
    public function createQuote()
    {
        // Create the quote instance
        $quote = $this->quoteFactory->create();
        $quote->setStore($this->storeManager->getStore());

        // Set the quote currency
        $currency = $this->storeManager->getStore()->getCurrentCurrency();
        $quote->setCurrency($currency);

        // Set the quote customer
        $quote->assignCustomer($this->shopperHandler->getCustomer());

        return $quote;
    }  

    /**
     * Checks if a quote exists and is valid
     */
    public function isQuote($quote)
    {
        return $quote
        && is_object($quote)
        && method_exists($quote, 'getId')
        && $quote->getId() > 0;
    }

    /**
     * Get the order increment id from a quote
     */
    public function getReference($quote)
    {
        try {
            return $quote->reserveOrderId()
                ->save()
                ->getReservedOrderId();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Prepares a quote for order placement
     */
    public function prepareQuote($fields = [], $methodId)
    {
        // Find quote and perform tasks
        $quote = $this->getQuote($fields);
        if ($this->isQuote($quote)) {
            // Prepare the inventory
            $quote->setInventoryProcessed(false);

            // Check for guest user quote
            if ($this->shopperHandler->isLoggedIn() === false) {
                $quote = $this->prepareGuestQuote($quote);
            }

            // Set the payment information
            $payment = $quote->getPayment();
            $payment->setMethod($methodId);
            $payment->save();

            return $quote;
        }

        return null;
    }

    /**
     * Sets the email for guest users
     */
    public function prepareGuestQuote($quote, $email = null)
    {
        // Retrieve the user email
        $guestEmail = ($email) ? $email : $this->findEmail($quote);

         // Set the quote as guest
        $quote->setCustomerId(null)
            ->setCustomerEmail($guestEmail)
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);

        // Delete the cookie
        $this->cookieManager->deleteCookie(
             $this->config->getValue('email_cookie_name')
        );

        // Return the quote
        return $quote;
    }

    /**
     * Find a customer email
     */
    public function findEmail($quote)
    {
        return $quote->getCustomerEmail()
        ?? $quote->getBillingAddress()->getEmail()
        ?? $this->cookieManager->getCookie(
            $this->config->getValue('email_cookie_name')
        );
    }

    /**
     * Gets an array of quote parameters
     */
    public function getQuoteData() {
        try {
            return [
                'value' => $this->getQuoteValue(),
                'currency' => $this->getQuoteCurrency()
            ];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Gets a quote currency
     */
    public function getQuoteCurrency() {
        try {            
            return $this->getQuote()->getQuoteCurrencyCode() 
            ?? $this->storeManager->getStore()->getCurrentCurrency()->getCode();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Gets a quote value
     */
    public function getQuoteValue() {
        try {            
            return $this->getQuote()
            ->collectTotals()
            ->save()
            ->getGrandTotal();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Add product items to a quote
     */
    public function addItems(Quote $quote, array $items = []) {
        foreach ($items as $item) {
            if (isset($item['id']) && (int) $item['id'] > 0) {
                // Load the product
                $product = $this->productModel->load($item['id']);

                // Get the quantity
                $quantity = isset($item['quantity']) && (int) $item['quantity'] > 0
                ? $item['quantity'] : 1;

                // Add the item
                $quote->addProduct($product, $quantity);
            }
        }

        // Return the quote
        return $quote;
    }
}