/**
 * Openpay_Codi Magento JS component
 *
 * @category    Openpay
 * @package     Openpay_Codi
 * @author      Jose Romero
 * @copyright   Openpay (http://openpay.mx)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Openpay_Codi/payment/openpay-offline'
            },
            getImageCodi: function() {
                return window.checkoutConfig.openpay_codi.image_pse;
            }
        });
    }
);