<?php

namespace AmeliaBooking\Application\Services\Helper;

use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\LoginType;
use AmeliaBooking\Infrastructure\Common\Container;
use Firebase\JWT\JWT;
use Interop\Container\Exception\ContainerException;
use DateTime;
use Exception;

/**
 * Class HelperService
 *
 * @package AmeliaBooking\Application\Services\Helper
 */
class HelperService
{
    private $container;

    /**
     * HelperService constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Returns formatted price based on price plugin settings
     *
     * @param int|float $price
     *
     * @return string
     * @throws ContainerException
     */
    public function getFormattedPrice($price)
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $paymentSettings = $settingsService->getCategorySettings('payments');

        // Price Separators
        $thousandSeparatorMap = [',', '.', ' ', ' '];
        $decimalSeparatorMap = ['.', ',', '.', ','];

        $thousandSeparator = $thousandSeparatorMap[$paymentSettings['priceSeparator'] - 1];
        $decimalSeparator = $decimalSeparatorMap[$paymentSettings['priceSeparator'] - 1];

        // Price Prefix
        $pricePrefix = '';
        if ($paymentSettings['priceSymbolPosition'] === 'before') {
            $pricePrefix = $paymentSettings['symbol'];
        } elseif ($paymentSettings['priceSymbolPosition'] === 'beforeWithSpace') {
            $pricePrefix = $paymentSettings['symbol'] . ' ';
        }

        // Price Suffix
        $priceSuffix = '';
        if ($paymentSettings['priceSymbolPosition'] === 'after') {
            $priceSuffix = $paymentSettings['symbol'];
        } elseif ($paymentSettings['priceSymbolPosition'] === 'afterWithSpace') {
            $priceSuffix = ' ' . $paymentSettings['symbol'];
        }

        $formattedNumber = number_format(
            $price,
            $paymentSettings['priceNumberOfDecimals'],
            $decimalSeparator,
            $thousandSeparator
        );

        return $pricePrefix . $formattedNumber . $priceSuffix;
    }

    /**
     * @param int $seconds
     *
     * @return string
     */
    public function secondsToNiceDuration($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = $seconds / 60 % 60;

        return ($hours ? ($hours . 'h ') : '') . ($hours && $minutes ? ' ' : '') . ($minutes ? ($minutes . 'min') : '');
    }

    /**
     * @param string $email
     * @param string $secret
     * @param int    $expireTimeStamp
     * @param int    $loginType
     *
     * @return mixed
     * @throws Exception
     */
    public function getGeneratedJWT($email, $secret, $expireTimeStamp, $loginType)
    {
        $now = new DateTime();

        $data = [
            'iss'   => AMELIA_SITE_URL,
            'iat'   => $now->getTimestamp(),
            'email' => $email,
            'wp'    => $loginType
        ];

        if ($expireTimeStamp !== null) {
            $data['exp'] = $expireTimeStamp;
        }

        return JWT::encode($data, $secret);
    }

    /**
     * @param string $email
     * @param string $type
     * @param string $dateStartString
     * @param string $dateEndString
     *
     * @return string
     *
     * @throws ContainerException
     * @throws Exception
     */
    public function getCustomerCabinetUrl($email, $type, $dateStartString, $dateEndString)
    {
        /** @var SettingsService $cabinetSettings */
        $cabinetSettings = $this->container->get('domain.settings.service')->getSetting('roles', 'customerCabinet');

        $cabinetPlaceholder = '';

        if ($cabinetURL = trim($cabinetSettings['pageUrl'])) {
            $tokenParam = $type === 'email' ? (strpos($cabinetURL, '?') === false ? '?token=' : '&token=') .
                $this->getGeneratedJWT(
                    $email,
                    $cabinetSettings['urlJwtSecret'],
                    DateTimeService::getNowDateTimeObject()->getTimestamp() + $cabinetSettings['tokenValidTime'],
                    LoginType::AMELIA_URL_TOKEN
                ) : '';

            $cabinetPlaceholder = substr($cabinetURL, -1) === '/' ?
                substr($cabinetURL, 0, -1) . $tokenParam : $cabinetURL . $tokenParam;

            if ($cabinetSettings['filterDate'] && $dateStartString && $dateEndString) {
                $cabinetPlaceholder .= '&end=' . $dateEndString . '&start=' . $dateStartString;
            }
        }

        return $cabinetPlaceholder;
    }
}
