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

class Confirm extends \Magento\Framework\App\Action\Action
{
    protected $resultPageFactory;
    protected $checkoutSession;
    protected $logger;
    
    /**
     * 
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger_interface
     */
    public function __construct(
            Context $context, 
            PageFactory $resultPageFactory, 
            \Magento\Checkout\Model\Session $checkoutSession,
            \Psr\Log\LoggerInterface $logger_interface
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger_interface;
    }
    /**
     * Load the page defined in view/frontend/layout/openpay_codi_confirm.xml
     * URL /openpay/payment/confirm
     * 
     * @url https://magento.stackexchange.com/questions/197310/magento-2-redirect-to-final-checkout-page-checkout-success-failed?rq=1
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute() {                
        try {                        
            $order_id = $this->checkoutSession->getLastOrderId();
            $quote_id = $this->checkoutSession->getLastQuoteId();
            
            $this->checkoutSession->setLastSuccessQuoteId($quote_id);
            
            $this->logger->debug('getLastQuoteId: '.$quote_id);
            $this->logger->debug('getLastOrderId: '.$order_id);
            $this->logger->debug('getLastSuccessQuoteId: '.$this->checkoutSession->getLastSuccessQuoteId());
            $this->logger->debug('getLastRealOrderId: '.$this->checkoutSession->getLastRealOrderId());        
            
            $this->logger->debug('#CoDi', array('redirect' => 'checkout/payment/success'));
            return $this->resultPageFactory->create();
        } catch (\Exception $e) {
            $this->logger->error('#CoDi', array('message' => $e->getMessage(), 'code' => $e->getCode(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()));
            //throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
        
        return $this->resultRedirectFactory->create()->setPath('checkout/cart'); 
    }
}