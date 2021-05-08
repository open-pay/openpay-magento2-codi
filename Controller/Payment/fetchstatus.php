<?php
/** 
 * @category    Payments
 * @package     Openpay_Codi
 * @author      Jose Romero
 * @copyright   Openpay (http://openpay.mx)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Openpay\Codi\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Openpay\Codi\Model\Payment as OpenpayPayment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\ResultFactory;

/**
 * FetchStatus class 
 */
class FetchStatus extends \Magento\Framework\App\Action\Action
{
    
    protected $request;
    protected $payment;
    protected $logger;
    protected $invoiceService;
    protected $jsonHelper;
    protected $orderFactory;
    
    public function __construct(
            Context $context,             
            \Magento\Framework\App\Request\Http $request,
            OpenpayPayment $payment, 
            \Psr\Log\LoggerInterface $logger_interface,
            \Magento\Sales\Model\Service\InvoiceService $invoiceService,
            \Magento\Framework\Json\Helper\Data $jsonHelper,
            \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        parent::__construct($context);        
        $this->request = $request;
        $this->payment = $payment;
        $this->logger = $logger_interface;        
        $this->invoiceService = $invoiceService;
        $this->jsonHelper = $jsonHelper;
        $this->orderFactory = $orderFactory;
    }
    
    public function execute() {
        $this->logger->debug('#FetchStatus');

        $this->logger->debug('#getRequest', array($this->getRequest()->getPost('order_id')));

        $orderIncrementId = $this->getRequest()->getPost('order_id');
        $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData(['status' => $order->getStatus()]);
        return $resultJson;
    }
}
