<?php

namespace AmeliaBooking\Application\Commands\PaymentGateway;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Booking\BookingApplicationService;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
use AmeliaBooking\Domain\Entity\Booking\Reservation;
use AmeliaBooking\Domain\Entity\Booking\Validator;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Services\Reservation\ReservationServiceInterface;
use AmeliaBooking\Domain\ValueObjects\String\PaymentType;
use AmeliaBooking\Infrastructure\Services\Payment\PayPalService;
use AmeliaBooking\Infrastructure\WP\Translations\FrontendStrings;

/**
 * Class PayPalPaymentCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\PaymentGateway
 */
class PayPalPaymentCommandHandler extends CommandHandler
{
    public $mandatoryFields = [
        'bookings',
        'payment'
    ];

    /**
     * @param PayPalPaymentCommand $command
     *
     * @return CommandResult
     * @throws \AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     * @throws \Exception
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function handle(PayPalPaymentCommand $command)
    {
        $result = new CommandResult();

        $this->checkMandatoryFields($command);

        $type = $command->getField('type') ?: Entities::APPOINTMENT;

        /** @var ReservationServiceInterface $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($type);
        /** @var BookingApplicationService $bookingAS */
        $bookingAS = $this->container->get('application.booking.booking.service');
        /** @var PaymentApplicationService $paymentAS */
        $paymentAS = $this->container->get('application.payment.service');

        $validator = new Validator();

        $validator->setCouponValidation(true);
        $validator->setCustomFieldsValidation(true);
        $validator->setTimeSlotValidation(true);

        /** @var Reservation $reservation */
        $reservation = $reservationService->processBooking(
            $result,
            $bookingAS->getAppointmentData($command->getFields()),
            $validator,
            false
        );

        if ($result->getResult() === CommandResult::RESULT_ERROR) {
            return $result;
        }

        $additionalInformation = $paymentAS->getBookingInformationForPaymentSettings(
            $reservation->getReservation(),
            $reservation->getBooking(),
            PaymentType::PAY_PAL
        );

        if ($result->getResult() === CommandResult::RESULT_ERROR) {
            return $result;
        }

        $paymentAmount = $reservationService->getReservationPaymentAmount($reservation);

        if (!$paymentAmount) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData([
                'paymentSuccessful' => false,
                'onSitePayment' => true
            ]);

            return $result;
        }

        /** @var PayPalService $paymentService */
        $paymentService = $this->container->get('infrastructure.payment.payPal.service');

        $response = $paymentService->execute(
            [
                'returnUrl'   => AMELIA_ACTION_URL . '/payment/payPal/callback&status=true',
                'cancelUrl'   => AMELIA_ACTION_URL . '/payment/payPal/callback&status=false',
                'amount'      => $paymentAmount,
                'description' => $additionalInformation['description']
            ]
        );

        if (!$response->isSuccessful()) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData([
                'paymentSuccessful' => false
            ]);

            return $result;
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setData([
            'paymentID'            => $response->getData()['id'],
            'transactionReference' => $response->getTransactionReference(),
        ]);

        return $result;
    }
}
