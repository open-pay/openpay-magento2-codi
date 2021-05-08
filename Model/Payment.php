<?php
/** 
 * @category    Payments
 * @package     Openpay_Codi
 * @author      Jose Romero
 * @copyright   Openpay (http://openpay.mx)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Openpay\Codi\Model;

use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * Class Payment
 *
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 */
class Payment extends \Magento\Payment\Model\Method\AbstractMethod
{

    const CODE = 'openpay_codi';

    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canOrder = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_isOffline = true;
    protected $scope_config;
    protected $openpay = false;
    protected $is_sandbox;
    protected $merchant_id = null;
    protected $sk = null;
    protected $sandbox_merchant_id;
    protected $sandbox_sk;
    protected $live_merchant_id;
    protected $live_sk;
    protected $supported_currency_codes = array('MXN');
    protected $logger;
    protected $_storeManager;
    protected $_inlineTranslation;
    protected $_directoryList;
    protected $_file;
    protected $expiration;
    protected $deadline;
    protected $timeUnit;

    /**
     * 
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Psr\Log\LoggerInterface $logger_interface
     * @param \Magento\Framework\App\Filesystem\DirectoryList $directoryList
     * @param \Magento\Framework\Filesystem\Io\File $file
     * @param Customer $customerModel
     * @param CustomerSession $customerSession
     * @param \Openpay\Codi\Model\OpenpayCustomerFactory $openpayCustomerFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context, 
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory, 
        \Magento\Payment\Helper\Data $paymentData, 
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Psr\Log\LoggerInterface $logger_interface,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        \Magento\Framework\Filesystem\Io\File $file,
        \Magento\Framework\View\Asset\Repository $assetRepository,
            Customer $customerModel,
            CustomerSession $customerSession,            
            \Openpay\Codi\Model\OpenpayCustomerFactory $openpayCustomerFactory,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry, 
            $extensionFactory,
            $customAttributeFactory,
            $paymentData, 
            $scopeConfig,
            $logger,
            null,
            null,
            $data
        );
        
        $this->customerModel = $customerModel;
        $this->customerSession = $customerSession;
        $this->openpayCustomerFactory = $openpayCustomerFactory;
        $this->assetRepository = $assetRepository;

        $this->_file = $file;
        $this->_directoryList = $directoryList;
        $this->logger = $logger_interface;
        $this->_inlineTranslation = $inlineTranslation;        
        $this->_storeManager = $storeManager;
        $this->scope_config = $scopeConfig;

        $this->is_active = $this->getConfigData('active');
        $this->is_sandbox = $this->getConfigData('is_sandbox');
        
        $this->sandbox_merchant_id = $this->getConfigData('sandbox_merchant_id');
        $this->sandbox_sk = $this->getConfigData('sandbox_sk');
        $this->live_merchant_id = $this->getConfigData('live_merchant_id');
        $this->live_sk = $this->getConfigData('live_sk');        
        $this->expiration = $this->getConfigData('expiration');
        $this->deadline = $this->getConfigData('deadline');
        $this->timeUnit = $this->getConfigData('time_unit');

        $this->merchant_id = $this->is_sandbox ? $this->sandbox_merchant_id : $this->live_merchant_id;
        $this->sk = $this->is_sandbox ? $this->sandbox_sk : $this->live_sk;
    }

    /**
     * 
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return \Openpay\Codi\Model\Payment
     * @throws \Magento\Framework\Validator\Exception
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount) {        
        unset($_SESSION['barcode_base64']);
        unset($_SESSION['due_date']);
        
        /**
         * Magento utiliza el timezone UTC, por lo tanto sobreescribimos este 
         * por la configuración que se define en el administrador         
         */
        $store_tz = $this->scope_config->getValue('general/locale/timezone');
        date_default_timezone_set($store_tz);

        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        /** @var \Magento\Sales\Model\Order\Address $billing */
        $billing = $order->getBillingAddress();
        
        $this->logger->debug('#order', array('$order_id' => $order->getIncrementId(), '$status' => $order->getStatus(), '$amount' => $amount));        

        try {

            $customer_data = array(
                'requires_account' => false,
                'name' => $billing->getFirstname(),
                'last_name' => $billing->getLastname(),
                'phone_number' => $billing->getTelephone(),
                'email' => $order->getCustomerEmail()
            );
            
            if ($this->validateAddress($billing)) {
                $customer_data = $this->formatAddress($customer_data, $billing);
            }     
            
            $this->logger->debug('#order', array('$customer_data' => $customer_data));        
            
            $codi_options = array('mode' => 'QR_CODE');

            $charge_request = array(
                'method' => 'codi',
                'amount' => $amount,
                'currency' => strtolower($order->getBaseCurrencyCode()),
                'description' => sprintf('ORDER #%s, %s', $order->getIncrementId(), $order->getCustomerEmail()),
                'order_id' => $order->getIncrementId(),
                "codi_options" => $codi_options,
                'customer' => $customer_data
            );

            if($this->expiration){
                $due_date = date('Y-m-d\TH:i:s', strtotime('+ '.$this->deadline.' '.$this->timeUnit));
                $charge_request['due_date'] = $due_date;
            }
            
            $this->logger->debug('#order', array('$charge_request' => $charge_request));        
            
            // Realiza la transacción en Openpay
            $charge = $this->makeOpenpayCharge($customer_data, $charge_request);  
            $charge_id = '';
            
            if ($charge->id) {
                $charge_id = $charge->id;
            } elseif(!$charge->id && $charge->redirect_url) {
                //$url_array = explode('/', $charge->redirect_url);
                //$charge_id = $url_array[8];
            }
                        
            $payment->setTransactionId($charge_id);
            
            $openpayCustomerFactory = $this->customerSession->isLoggedIn() ? $this->hasOpenpayAccount($this->customerSession->getCustomer()->getId()) : null;
            $openpay_customer_id = $openpayCustomerFactory ? $openpayCustomerFactory->openpay_id : null;
            
            // Actualiza el estado de la orden
            $state = \Magento\Sales\Model\Order::STATE_NEW;
            $order->setState($state)->setStatus($state);
            
            // Registra el ID de la transacción de Openpay
            $order->setExtOrderId($charge_id);
            // Registra (si existe), el ID de Customer de Openpay
            $order->setExtCustomerId($openpay_customer_id);
            $order->save();

            $base_url = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
            
            if($charge->payment_method->barcode_base64){
                $_SESSION['barcode_base64'] = $charge->payment_method->barcode_base64;
                $_SESSION['due_date'] = $charge->due_date;
                $_SESSION['openpay_codi_redirect_url'] = $base_url.'openpay/payment/confirm';
            }
            
        } catch (\Exception $e) {
            $this->debugData(['exception' => $e->getMessage()]);
            $this->_logger->error(__( $e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__($this->error($e)));
        }

        $payment->setSkipOrderProcessing(true);
        return $this;
    }
    
    private function makeOpenpayCharge($customer_data, $charge_request) {        
        $openpay = $this->getOpenpayInstance();        

        // Cargo para usuarios "invitados"
        if (!$this->customerSession->isLoggedIn()) {            
            return $openpay->charges->create($charge_request);
        }     

        // Se remueve el atributo de "customer" porque ya esta relacionado con una cuenta en Openpay
        unset($charge_request['customer']); 

        $openpay_customer = $this->retrieveOpenpayCustomerAccount($customer_data);    
                
        try {
            // Cargo para usuarios con cuenta
            $this->logger->debug('#makeOpenpayCharge', array('bank_account' => true));          
            return $openpay_customer->charges->create($charge_request);            
                    
        } catch (\Exception $e) {             
            $this->logger->critical('#makeOpenpayCharge', array('error' => $e->getMessage()));   
            $this->logger->critical('#makeOpenpayCharge', array('getTraceAsString' => $e->getTraceAsString()));   
            return false;
        }        
    }
    
    private function retrieveOpenpayCustomerAccount($customer_data) {
        try {
            $customerId = $this->customerSession->getCustomer()->getId();                
            
            $has_openpay_account = $this->hasOpenpayAccount($customerId);
            if ($has_openpay_account === false) {
                $openpay_customer = $this->createOpenpayCustomer($customer_data);
                $this->logger->debug('$openpay_customer => '.$openpay_customer->id);

                $data = [
                    'customer_id' => $customerId,
                    'openpay_id' => $openpay_customer->id
                ];

                // Se guarda en BD la relación
                $openpay_customer_local = $this->openpayCustomerFactory->create();
                $openpay_customer_local->addData($data)->save();                    
            } else {
                $openpay_customer = $this->getOpenpayCustomer($has_openpay_account->openpay_id);
                if($openpay_customer === false){
                    $openpay_customer = $this->createOpenpayCustomer($customer_data);
                    $this->logger->debug('#update openpay_customer', array('$openpay_customer_old' => $has_openpay_account->openpay_id, '$openpay_customer_old_new' => $openpay_customer->id)); 

                    // Se actualiza en BD la relación
                    $openpay_customer_local = $this->openpayCustomerFactory->create();
                    $openpay_customer_local_update = $openpay_customer_local->load($has_openpay_account->openpay_customer_id);
                    $openpay_customer_local_update->setOpenpayId($openpay_customer->id);
                    $openpay_customer_local_update->save();
                }
            }
            
            return $openpay_customer;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
    }
    
    private function createOpenpayCustomer($data) {
        try {
            $openpay = $this->getOpenpayInstance();
            return $openpay->customers->add($data);            
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }        
    }
    
    private function hasOpenpayAccount($customer_id) {        
        try {
            $openpay_customer_local = $this->openpayCustomerFactory->create();
            $response = $openpay_customer_local->fetchOneBy('customer_id', $customer_id);
            return $response;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }  
    }
    
    public function getOpenpayCustomer($openpay_customer_id) {
        try {
            $openpay = $this->getOpenpayInstance();
            $customer = $openpay->customers->get($openpay_customer_id);
            if(isset($customer->balance)){
                return false;
            }
            return $customer;             
        } catch (\Exception $e) {
            return false;
        }        
    }
    
    public function getOpenpayCharge($charge_id, $customer_id = null) {
        try {                        
            if ($customer_id === null) {                
                $openpay = $this->getOpenpayInstance();
                return $openpay->charges->get($charge_id);
            }            
            
            $openpay_customer = $this->getOpenpayCustomer($customer_id);

            if($openpay_customer === false){
                $openpay = $this->getOpenpayInstance();
                return $openpay->charges->get($charge_id);
            }

            return $openpay_customer->charges->get($charge_id);            
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
    }
    
    private function formatAddress($customer_data, $billing) {
        $customer_data['address'] = array(
            'line1' => $billing->getStreetLine(1),
            'line2' => $billing->getStreetLine(2),
            'postal_code' => $billing->getPostcode(),
            'city' => $billing->getCity(),
            'state' => $billing->getRegion(),
            'country_code' => $billing->getCountryId()
        );
        return $customer_data;
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode) {
        return in_array($currencyCode, $this->supported_currency_codes);
    }
    
    /**
     * Get $sk property
     * 
     * @return string
     */
    public function getSecretKey() {
        return $this->sk;
    }

    /**
     * Get $merchant_id property
     * 
     * @return string
     */
    public function getMerchantId() {
        return $this->merchat_id;
    }

    /**
     * Get $is_sandbox property
     * 
     * @return boolean
     */
    public function isSandbox() {
        return $this->is_sandbox;
    }

    /**
     * @param Exception $e
     * @return string
     */
    public function error($e) {

        /* 6001 el webhook ya existe */
        switch ($e->getCode()) {
            case '1000':
            case '1004':
            case '1005':
                $msg = 'Servicio no disponible.';
                break;
            case '6001':
                $msg = 'El webhook ya existe, has caso omiso de este mensaje.';
            case '6002':
                $msg = 'El webhook no pudo ser verificado, revisa la URL.';
            default: /* Demás errores 400 */
                $msg = 'La petición no pudo ser procesada.';
                break;
        }

        return 'ERROR '.$e->getCode().'. '.$msg;
    }

    /**
     * @param Address $billing
     * @return boolean
     */
    public function validateAddress($billing) {
        return ($billing->getCountryId() && $billing->getStreetLine(1) && $billing->getCity() && $billing->getPostcode() && $billing->getRegion());
    }

    /**
     * Create webhook
     * @return mixed
     */
    public function createWebhook() {
        $base_url = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $uri = $base_url."openpay/index/webhook";
        $webhook_data = array(
            'url' => $uri,
            'event_types' => array(
                'verification',
                'charge.succeeded',
                'charge.created',
                'charge.cancelled',
                'charge.failed',
                'payout.created',
                'payout.succeeded',
                'payout.failed',
                'spei.received',
                'chargeback.created',
                'chargeback.rejected',
                'chargeback.accepted',
                'transaction.expired'
            )
        );

        $openpay = \Openpay::getInstance($this->merchant_id, $this->sk);
        \Openpay::setSandboxMode($this->is_sandbox);

        $userAgent = "Openpay-MTO2MX/v2";
        \Openpay::setUserAgent($userAgent);

        try {
            $webhook = $openpay->webhooks->add($webhook_data);
            return $webhook;
        } catch (Exception $e) {
            return $this->error($e);
        }
    }
    
    /*
     * Validate if host is secure (SSL)
     */
    public function hostSecure() {
        $is_secure = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $is_secure = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            $is_secure = true;
        }
        
        return $is_secure;
    }

    public function getImagePath()
    {
        $fileId = 'Openpay_Codi::images/logo_pse.png';
        $params = [
            'area' => 'frontend'
        ];
        $asset = $this->assetRepository->createAsset($fileId, $params);
        try {
            return $asset->getUrl();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getOpenpayInstance() {
        $openpay = \Openpay::getInstance($this->merchant_id, $this->sk);
        \Openpay::setSandboxMode($this->is_sandbox);
        
        $userAgent = "Openpay-MTO2MX/v2";
        \Openpay::setUserAgent($userAgent);
        
        return $openpay;
    }

}
