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

namespace CheckoutCom\Magento2\Model\Config\Backend\Source;

/**
 * Class ConfigPayPalButtonColor
 */
class ConfigPayPalButtonColor implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * Possible PayPal button color
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'gold',
                'label' => __('Gold - Default')
            ],
            [
                'value' => 'blue',
                'label' => __('Blue')
            ],
            [
                'value' => 'white',
                'label' => __('White')
            ],
            [
                'value' => 'silver',
                'label' => __('Silver')
            ],
            [
                'value' => 'black',
                'label' => __('Black')
            ],
        ];
    }
}
