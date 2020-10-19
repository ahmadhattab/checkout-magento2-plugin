<?php
/**
 * Checkout.com
 * Authorized and regulated as an electronic money institution
 * by the UK Financial Conduct Authority (FCA) under number 900816.
 *
 * PHP version 7
 *
 * @category  Magento2
 * @package   Checkout.com
 * @author    Platforms Development Team <platforms@checkout.com>
 * @copyright 2010-2019 Checkout.com
 * @license   https://opensource.org/licenses/mit-license.html MIT License
 * @link      https://docs.checkout.com/
 */

namespace CheckoutCom\Magento2\Plugin\Backend;

use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Class OrderAfterSave.
 */
class OrderAfterSave
{
    /**
     * @var Session
     */
    public $backendAuthSession;

    /**
     * @var WebhookHandlerService
     */
    public $webhookHandler;

    /**
     * @var TransactionHandlerService
     */
    public $transactionHandler;

    /**
     * @var Config
     */
    public $config;

    /**
     * @var RequestInterface
     */
    public $request;

    /**
     * OrderAfterSave constructor.
     */
    public function __construct(
        \Magento\Backend\Model\Auth\Session $backendAuthSession,
        \CheckoutCom\Magento2\Model\Service\WebhookHandlerService $webhookHandler,
        \CheckoutCom\Magento2\Model\Service\TransactionHandlerService $transactionHandler,
        \CheckoutCom\Magento2\Gateway\Config\Config $config,
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->backendAuthSession = $backendAuthSession;
        $this->webhookHandler = $webhookHandler;
        $this->transactionHandler = $transactionHandler;
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * Create transactions for the order.
     * @param OrderRepositoryInterface $orderRepository
     * @param $order
     * @return mixed
     */
    public function afterSave(OrderRepositoryInterface $orderRepository, $order)
    {
        if ($this->backendAuthSession->isLoggedIn()) {
            // Get the method ID
            $methodId = $order->getPayment()->getMethodInstance()->getCode();

            // Process the webhooks if order is not on hold
            if (in_array($methodId, $this->config->getMethodsList()) 
                && $order->getState() != 'holded'
                && $this->needsWebhookProcessing()) {
                    $this->webhookHandler->processAllWebhooks($order);
            }
        }

        return $order;
    }

    /**
     * Don't process the stored webhooks after admin refund.
     * 
     * @return bool
     */
    public function needsWebhookProcessing()
    {
        $params = $this->request->getParams();
        
        return isset($params['creditmemo']) ? false : true;
    }
}
