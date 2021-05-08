<?php
/**
 * Copyright © 2015 Pay.nl All rights reserved.
 */

namespace Openpay\Codi\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Openpay\Codi\Model\Payment as OpenpayPayment;
use Magento\Checkout\Model\Cart;

class OpenpayConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCodes = [
        'openpay_codi',        
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];
    
    /**
     * @var \Openpay\Codi\Model\Payment
     */
    protected $payment ;

    protected $cart;


    /**     
     * @param PaymentHelper $paymentHelper
     * @param OpenpayPayment $payment
     */
    public function __construct(PaymentHelper $paymentHelper, OpenpayPayment $payment, Cart $cart) {        
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
        $this->cart = $cart;
        $this->payment = $payment;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {                
        $config = [];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['openpay_codi']['image_pse'] = $this->payment->getImagePath();
            }
        }
                
        return $config;
    }       
    
}
