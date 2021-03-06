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

namespace CheckoutCom\Magento2\Observer\Api;

use Magento\Framework\Event\Observer;

/**
 * Class OrderCreditMemoSaveAfter.
 */
class OrderCreditMemoSaveAfter implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var Session
     */
    public $backendAuthSession;

    /**
     * @var RequestInterface
     */
    public $request;

    /**
     * @var Config
     */
    public $config;

    /**
     * @var Array
     */
    public $params;

    /**
     * OrderSaveBefore constructor.
     */
    public function __construct(
        \Magento\Backend\Model\Auth\Session $backendAuthSession,
        \Magento\Framework\App\RequestInterface $request,
        \CheckoutCom\Magento2\Gateway\Config\Config $config
    ) {
        $this->backendAuthSession = $backendAuthSession;
        $this->request = $request;
        $this->config = $config;
    }

    /**
     * Run the observer.
     */
    public function execute(Observer $observer)
    {
        $creditMemo = $observer->getEvent()->getCreditmemo();
        $order = $creditMemo->getOrder();
        $methodId = $order->getPayment()->getMethodInstance()->getCode();

        // Check if payment method is checkout.com
        if (in_array($methodId, $this->config->getMethodsList())) {
            $status = $order->getStatus() == 'closed' ? 'closed' : $this->config->getValue('order_status_refunded');

            // Update the order status
            $order->setStatus($status);

            // Get the latest order status comment
            $orderComments = $order->getStatusHistories();
            $orderComment = array_pop($orderComments);

            // Update the order history comment status
            $orderComment->setData('status', $status)->save();
            $order->save();
        }

        return $this;
    }
}
