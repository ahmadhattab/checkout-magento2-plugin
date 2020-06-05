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

namespace CheckoutCom\Magento2\Controller\Adminhtml\LogReader\Logs;

class Index extends \Magento\Backend\App\Action
{
    /**
     * @var PageFactory
     */
    public $resultPageFactory;

    /**
     * @var JsonFactory
     */
    public $resultJsonFactory;

    /**
     * @var LogEntityFactory
     */
    public $logEntityFactory;

    /**
     * Index class constructor
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Naxero\Translation\Model\LogEntityFactory $logEntityFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logEntityFactory = $logEntityFactory;
    }

    /**
     * Index action
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        // Get the action
        $action = $this->getRequest()->getParam('action');

        // Process the request
        if ($this->getRequest()->isAjax()) {
            switch ($action) {
                case 'clear_logs':
                    return $this->clearLogs();

                default:
                    return $this->getData();
            }
        }

        // Default for normal page load
        return $this->getData();
    }

    /**
     * Clear the log records in database.
     */
    public function clearLogs()
    {
        // Prepare the output array
        $output = [
            'success' => true,
            'message' => __('The logs have been cleared successfully.')
        ];

        try {
            $logEntity = $this->logEntityFactory->create();
            $connection = $logEntity->getCollection()->getConnection();
            $tableName  = $logEntity->getCollection()->getMainTable();
            $connection->truncateTable($tableName);
        } catch (\Exception $e) {
            $output = [
                'success' => false,
                'message' => __($e->getMessage())
            ];
        }

        return $this->resultJsonFactory->create()->setData($output);
    }

    /**
     * Send the logs data for display.
     */
    public function getData()
    {
        return $this->resultPageFactory->create();
    }
}
