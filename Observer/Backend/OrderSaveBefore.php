<?php
/**
 * Checkout.com Magento 2 Payment module (https://www.checkout.com)
 *
 * Copyright (c) 2017 Checkout.com (https://www.checkout.com)
 * Author: David Fiaty | integration@checkout.com
 *
 * MIT License
 */

namespace CheckoutCom\Magento2\Observer\Backend;

use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Payment\Transaction;
use \Checkout\Models\Payments\TokenSource;
use \Checkout\Models\Payments\Payment;
class OrderSaveBefore implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var Session
     */
    protected $backendAuthSession;

    /**
     * @var Http
     */
    protected $request;

    /**
     * @var RemoteAddress
     */
    protected $remoteAddress;

    /**
     * @var ApiHandlerService
     */
    protected $apiHandler;

    /**
     * @var OrderHandlerService
     */
    protected $orderHandler;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Utilities
     */
    protected $utilities;

    /**
     * @var Array
     */
    protected $params;

    /**
     * @var Order
     */
    protected $order;

    /**
     * @var String
     */
    protected $methodId;

    /**
     * OrderSaveBefore constructor.
     */
    public function __construct(
        \Magento\Backend\Model\Auth\Session $backendAuthSession,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \CheckoutCom\Magento2\Model\Service\ApiHandlerService $apiHandler,
        \CheckoutCom\Magento2\Model\Service\OrderHandlerService $orderHandler,
        \CheckoutCom\Magento2\Gateway\Config\Config $config,
        \CheckoutCom\Magento2\Helper\Utilities $utilities
    ) {
        $this->backendAuthSession = $backendAuthSession;
        $this->request = $request;
        $this->remoteAddress = $remoteAddress;
        $this->apiHandler = $apiHandler;
        $this->orderHandler = $orderHandler;
        $this->config = $config;
        $this->utilities = $utilities;

        // Get the request parameters
        $this->params = $this->request->getParams();
    }
 
    /**
     * OrderSaveBefore constructor.
     */
    public function execute(Observer $observer)
    {
        // Get the order
        $this->order = $observer->getEvent()->getOrder();

        // Get the method id
        $this->methodId = $this->order->getPayment()->getMethodInstance()->getCode();

        // Process the payment
        if ($this->needsMotoProcessing()) {
            // Set the token source
            $tokenSource = new TokenSource($this->params['ckoCardToken']);

            // Set the payment
            $request = new Payment(
                $tokenSource, 
                $this->order->getOrderCurrencyCode()
            );

            // Prepare the capture date setting
            $captureDate = $this->config->getCaptureTime($this->methodId);
            
            // Set the request parameters
            $request->capture = $this->config->needsAutoCapture($this->methodId);
            $request->amount = $this->order->getGrandTotal()*100;
            $request->reference = $this->order->getIncrementId();
            $request->payment_ip = $this->remoteAddress->getRemoteAddress();
            if ($captureDate) {
                $request->capture_time = $this->config->getCaptureTime($this->methodId);
            }

            // Send the charge request
            $response = $this->apiHandler->checkoutApi
                ->payments()
                ->request($request);

            // Process the response
            $success = $this->apiHandler->isValidResponse($response);

            //  Add the response to the order
            if ($success) {
                $this->utilities->setPaymentData($this->order, $response);
            }
            else {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('The transaction could not be processed.')
                );
            }
        }
      
        return $this;
    }

    /**
     * Checks if the MOTO logic should be triggered.
     */
    protected function needsMotoProcessing() {
        return $this->backendAuthSession->isLoggedIn()
        && isset($this->params['ckoCardToken'])
        && $this->methodId == 'checkoutcom_moto'
        && !$this->orderHandler->hasTransaction($this->order, Transaction::TYPE_AUTH);
    }
}