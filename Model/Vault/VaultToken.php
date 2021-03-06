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

namespace CheckoutCom\Magento2\Model\Vault;

use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Class VaultToken
 */
class VaultToken
{
    /**
     * @var PaymentTokenFactoryInterface
     */
    public $paymentTokenFactory;

    /**
     * @var EncryptorInterface
     */
    public $encryptor;

    /**
     * @var CardHandlerService
     */
    public $cardHandler;

    /**
     * VaultToken constructor.
     */
    public function __construct(
        \Magento\Vault\Api\Data\PaymentTokenFactoryInterface $paymentTokenFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \CheckoutCom\Magento2\Model\Service\CardHandlerService $cardHandler
    ) {
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->encryptor = $encryptor;
        $this->cardHandler = $cardHandler;
    }

    /**
     * Returns the prepared payment token.
     *
     * @param  array    $card
     * @param  int|null $customerId
     * @return PaymentTokenInterface
     */
    public function create(array $card, $methodId, $customerId = null)
    {
        $expiryMonth    = str_pad($card['expiry_month'], 2, '0', STR_PAD_LEFT);
        $expiryYear     = $card['expiry_year'];
        $expiresAt      = $this->getExpirationDate($expiryMonth, $expiryYear);
        $cardScheme      = $card['scheme'];

        /**
         * @var PaymentTokenInterface $paymentToken
         */
        $paymentToken = $this->paymentTokenFactory->create($this->paymentTokenFactory::TOKEN_TYPE_CREDIT_CARD);
        $paymentToken->setExpiresAt($expiresAt);

        if (array_key_exists('id', $card)) {
            $paymentToken->setGatewayToken($card['id']);
        }

        $tokenDetails = [
            'type'              => $this->cardHandler->getCardCode($cardScheme),
            'maskedCC'          => $card['last4'],
            'expirationDate'    => $expiryMonth . '/' . $expiryYear,
        ];

        $paymentToken->setTokenDetails($this->convertDetailsToJSON($tokenDetails));
        $paymentToken->setIsActive(true);
        $paymentToken->setPaymentMethodCode($methodId);

        if ($customerId) {
            $paymentToken->setCustomerId($customerId);
        }

        $paymentToken->setPublicHash($this->generatePublicHash($paymentToken));

        return $paymentToken;
    }

    /**
     * Returns the date time object with the given expiration month and year.
     *
     * @param  string $expiryMonth
     * @param  string $expiryYear
     * @return string
     */
    private function getExpirationDate($expiryMonth, $expiryYear)
    {
        $expDate = new \DateTime(
            $expiryYear
            . '-'
            . $expiryMonth
            . '-'
            . '01'
            . ' '
            . '00:00:00',
            new \DateTimeZone('UTC')
        );

        return $expDate->add(new \DateInterval('P1M'))->format('Y-m-d 00:00:00');
    }

    /**
     * Generate vault payment public hash
     *
     * @param  PaymentTokenInterface $paymentToken
     * @return string
     */
    private function generatePublicHash(PaymentTokenInterface $paymentToken)
    {
        $hashKey = $paymentToken->getGatewayToken();

        if ($paymentToken->getCustomerId()) {
            $hashKey = $paymentToken->getCustomerId();
        }

        $hashKey .= $paymentToken->getPaymentMethodCode()
            . $paymentToken->getType()
            . $paymentToken->getTokenDetails();

        return $this->encryptor->getHash($hashKey);
    }

    /**
     * Returns the JSON object of the given data.
     *
     * Convert payment token details to JSON
     *
     * @param  array $details
     * @return string
     */
    private function convertDetailsToJSON(array $details)
    {
        $json = \Zend_Json::encode($details);
        return $json ?: '{}';
    }
}
