<?php

namespace AmeliaBooking\Application\Commands\Booking\Appointment;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Booking\Event\Event;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Services\Reservation\ReservationServiceInterface;
use AmeliaBooking\Domain\ValueObjects\BooleanValueObject;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use Slim\Exception\ContainerValueNotFoundException;
use Interop\Container\Exception\ContainerException;
use Exception;

/**
 * Class SuccessfulBookingCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Booking\Appointment
 */
class SuccessfulBookingCommandHandler extends CommandHandler
{
    /**
     * @var array
     */
    public $mandatoryFields = [
        'appointmentStatusChanged',
    ];

    /**
     * @param SuccessfulBookingCommand $command
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws ContainerValueNotFoundException
     * @throws QueryExecutionException
     * @throws ContainerException
     * @throws Exception
     */
    public function handle(SuccessfulBookingCommand $command)
    {
        $result = new CommandResult();

        $this->checkMandatoryFields($command);

        $type = $command->getField('type') ?: Entities::APPOINTMENT;

        /** @var ReservationServiceInterface $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($type);

        /** @var Appointment|Event $reservation */
        $reservation = $reservationService->getReservationByBookingId((int)$command->getArg('id'));

        /** @var CustomerBooking $booking */
        $booking = $reservation->getBookings()->getItem(
            (int)$command->getArg('id')
        );

        $booking->setChangedStatus(new BooleanValueObject(true));

        $recurringReservations = [];

        $recurring = isset($command->getFields()['recurring']) ? $command->getFields()['recurring'] : [];

        foreach ($recurring as $recurringData) {
            /** @var Appointment|Event $recurringReservation */
            $recurringReservation = $reservationService->getReservationByBookingId((int)$recurringData['id']);

            /** @var CustomerBooking $recurringBooking */
            $recurringBooking = $recurringReservation->getBookings()->getItem(
                (int)$recurringData['id']
            );

            $recurringBooking->setChangedStatus(new BooleanValueObject(true));

            $recurringReservations[] = $this->getResultData(
                $recurringReservation,
                $recurringBooking,
                $recurringData['appointmentStatusChanged']
            );
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully get booking');
        $result->setData(array_merge(
            $this->getResultData($reservation, $booking, $command->getFields()['appointmentStatusChanged']),
            [
                'recurring' => $recurringReservations
            ]
        ));

        $result->setDataInResponse(false);

        return $result;
    }

    /**
     * @param Appointment|Event $reservation
     * @param CustomerBooking   $booking
     * @param bool              $appointmentStatusChanged
     *
     * @return array
     */
    private function getResultData($reservation, $booking, $appointmentStatusChanged)
    {
        return [
            'type'                              => $reservation->getType()->getValue(),
            $reservation->getType()->getValue() => $reservation->toArray(),
            Entities::BOOKING                   => $booking->toArray(),
            'appointmentStatusChanged'          => $appointmentStatusChanged,
        ];
    }
}
