<?php
/**
 * Payment Charge Types Source Model
 *
 * @category    Openpay
 * @package     Openpay_Codi
 * @author      Jose Romero
 * @copyright   Openpay (http://openpay.mx)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Openpay\Codi\Model\Source;

class TimeUnit
{
    /**
     * @return array
     */
    public function getUnits()
    {
        return array(
            array('value' => 'minutes', 'label' => 'Minuto (s)'),
            array('value' => 'hours', 'label' => 'Hora (s)'),
            array('value' => 'days', 'label' => 'Dia (s)')            
        );     
    }
}
