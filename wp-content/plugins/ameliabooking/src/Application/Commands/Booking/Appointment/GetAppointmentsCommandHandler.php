<?php

namespace AmeliaBooking\Application\Commands\Booking\Appointment;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Booking\BookingApplicationService;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\AuthorizationException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;
use Interop\Container\Exception\ContainerException;

/**
 * Class GetAppointmentsCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Booking\Appointment
 */
class GetAppointmentsCommandHandler extends CommandHandler
{
    /**
     * @param GetAppointmentsCommand $command
     *
     * @return CommandResult
     *
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws AccessDeniedException
     * @throws ContainerException
     */
    public function handle(GetAppointmentsCommand $command)
    {
        $result = new CommandResult();

        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');
        /** @var SettingsService $settingsDS */
        $settingsDS = $this->container->get('domain.settings.service');
        /** @var UserApplicationService $userAS */
        $userAS = $this->container->get('application.user.service');
        /** @var BookingApplicationService $bookingAS */
        $bookingAS = $this->container->get('application.booking.booking.service');

        $params = $command->getField('params');

        $isCabinetPage = $command->getPage() === 'cabinet';

        try {
            /** @var AbstractUser $user */
            $user = $userAS->authorization($isCabinetPage ? $command->getToken() : null, $command->getCabinetType());
        } catch (AuthorizationException $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setData([
                'reauthorize' => true
            ]);

            return $result;
        }

        $readOthers = $this->container->getPermissionsService()->currentUserCanReadOthers(Entities::APPOINTMENTS);

        if ($params['dates']) {
            !empty($params['dates'][0]) ? $params['dates'][0] .= ' 00:00:00' : null;
            !empty($params['dates'][1]) ? $params['dates'][1] .= ' 23:59:59' : null;
        }

        /** @var Collection $appointments */
        $appointments = $appointmentRepository->getFiltered($params);

        $occupiedTimes = [];

        /** @var Appointment $appointment */
        foreach ($appointments->getItems() as $appointment) {
            /** @var CustomerBooking $booking */
            foreach ($appointment->getBookings()->getItems() as $booking) {
                // fix for wrongly saved JSON
                if ($booking->getCustomFields() &&
                    json_decode($booking->getCustomFields()->getValue(), true) === null
                ) {
                    $booking->setCustomFields(null);
                }
            }
        }

        $currentDateTime = DateTimeService::getNowDateTimeObject();

        $groupedAppointments = [];

        /** @var Appointment $appointment */
        foreach ($appointments->getItems() as $appointment) {
            $bookingsCount = 0;

            /** @var CustomerBooking $booking */
            foreach ($appointment->getBookings()->getItems() as $booking) {
                if ($bookingAS->isBookingApprovedOrPending($booking->getStatus()->getValue())) {
                    $bookingsCount++;
                }
            }

            $providerId = $appointment->getProviderId()->getValue();

            // skip appointments/bookings for other customers if user is customer, and remember that time/date values
            if ($userAS->isCustomer($user)) {
                /** @var CustomerBooking $booking */
                foreach ($appointment->getBookings()->getItems() as $bookingKey => $booking) {
                    if (!$user->getId() || $booking->getCustomerId()->getValue() !== $user->getId()->getValue()) {
                        $appointment->getBookings()->deleteItem($bookingKey);
                    }
                }

                if ($appointment->getBookings()->length() === 0) {
                    $serviceTimeBefore = $appointment->getService()->getTimeBefore() ?
                        $appointment->getService()->getTimeBefore()->getValue() : 0;

                    $serviceTimeAfter = $appointment->getService()->getTimeAfter() ?
                        $appointment->getService()->getTimeAfter()->getValue() : 0;

                    $occupiedTimeStart = DateTimeService::getCustomDateTimeObject(
                        $appointment->getBookingStart()->getValue()->format('Y-m-d H:i:s')
                    )->modify('-' . $serviceTimeBefore . ' second')->format('H:i:s');

                    $occupiedTimeEnd = DateTimeService::getCustomDateTimeObject(
                        $appointment->getBookingEnd()->getValue()->format('Y-m-d H:i:s')
                    )->modify('+' . $serviceTimeAfter . ' second')->format('H:i:s');

                    $occupiedTimes[$appointment->getBookingStart()->getValue()->format('Y-m-d')][] =
                        [
                            'employeeId' => $providerId,
                            'startTime'  => $occupiedTimeStart,
                            'endTime'    => $occupiedTimeEnd,
                        ];

                    continue;
                }
            }

            // skip appointments for other providers if user is provider
            if ((!$readOthers || $isCabinetPage) &&
                $user->getType() === Entities::PROVIDER &&
                $user->getId()->getValue() !== $providerId
            ) {
                continue;
            }

            $minimumCancelTimeInSeconds = $settingsDS
                ->getEntitySettings($appointment->getService()->getSettings())
                ->getGeneralSettings()
                ->getMinimumTimeRequirementPriorToCanceling();

            $minimumCancelTime = DateTimeService::getCustomDateTimeObject(
                $appointment->getBookingStart()->getValue()->format('Y-m-d H:i:s')
            )->modify("-{$minimumCancelTimeInSeconds} seconds");

            $date = $appointment->getBookingStart()->getValue()->format('Y-m-d');

            $cancelable = $currentDateTime <= $minimumCancelTime;

            if ($isCabinetPage &&
                $settingsDS->getSetting('general', 'showClientTimeZone') &&
                $userAS->isCustomer($user)
            ) {
                    $appointment->getBookingStart()->getValue()->setTimezone(new \DateTimeZone('UTC'));
                    $appointment->getBookingEnd()->getValue()->setTimezone(new \DateTimeZone('UTC'));
            }

            $groupedAppointments[$date]['date'] = $date;
            $groupedAppointments[$date]['appointments'][] = array_merge(
                $appointment->toArray(),
                [
                    'reschedulable' => $cancelable,
                    'cancelable'    => $cancelable,
                    'past'          => $currentDateTime >= $appointment->getBookingStart()->getValue()
                ]
            );
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully retrieved appointments');
        $result->setData([
            Entities::APPOINTMENTS => $groupedAppointments,
            'occupied'             => $occupiedTimes
        ]);

        return $result;
    }
}
