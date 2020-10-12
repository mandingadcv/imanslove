<?php

namespace AmeliaBooking\Application\Commands\Booking\Event;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\AuthorizationException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Booking\Event\Event;
use AmeliaBooking\Domain\Entity\Booking\Event\EventPeriod;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Reservation\ReservationServiceInterface;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventRepository;
use DateTimeZone;
use Interop\Container\Exception\ContainerException;

/**
 * Class GetEventsCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Booking\Event
 */
class GetEventsCommandHandler extends CommandHandler
{
    /**
     * @param GetEventsCommand $command
     *
     * @return CommandResult
     *
     * @throws ContainerException
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function handle(GetEventsCommand $command)
    {
        $result = new CommandResult();

        /** @var SettingsService $settingsDS */
        $settingsDS = $this->container->get('domain.settings.service');
        /** @var ReservationServiceInterface $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get(Entities::EVENT);
        /** @var EventRepository $eventRepository */
        $eventRepository = $this->container->get('domain.booking.event.repository');
        /** @var UserApplicationService $userAS */
        $userAS = $this->container->get('application.user.service');

        $params = $command->getField('params');

        /** @var AbstractUser $user */
        $user = null;

        $isFrontEnd = isset($params['page']);
        $isCabinetPage = $command->getPage() === 'cabinet';

        if (!$isFrontEnd) {
            try {
                /** @var AbstractUser $user */
                $user = $userAS->authorization(
                    $isCabinetPage ? $command->getToken() : null,
                    $command->getCabinetType()
                );
            } catch (AuthorizationException $e) {
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setData([
                    'reauthorize' => true
                ]);

                return $result;
            }

            if ($userAS->isAmeliaUser($user) && $userAS->isCustomer($user)) {
                $params['customerId'] = $user->getId()->getValue();
            }

            if ($user && $user->getType() === AbstractUser::USER_ROLE_PROVIDER) {
                $params['providers'] = [$user->getId()->getValue()];
            }
        }

        if (isset($params['dates'][0])) {
            $params['dates'][0] ? $params['dates'][0] .= ' 00:00:00' : null;
        }

        if (isset($params['dates'][1])) {
            $params['dates'][1] ? $params['dates'][1] .= ' 23:59:59' : null;
        }

        if ($isFrontEnd) {
            $params['show'] = 1;
        }

        $filteredEventIds = $eventRepository->getFilteredIds(
            $params,
            $settingsDS->getSetting('general', 'itemsPerPage')
        );

        if ($isCabinetPage) {
            $params['fetchCoupons'] = true;
        }

        /** @var Collection $events */
        $events = $filteredEventIds ?
            $eventRepository->getFiltered(array_merge($params, ['ids' => array_column($filteredEventIds, 'id')])) :
            new Collection();

        $currentDateTime = DateTimeService::getNowDateTimeObject();

        $eventsArray = [];

        /** @var Event $event */
        foreach ($events->getItems() as $event) {
            if ($isFrontEnd && !$event->getShow()->getValue()) {
                continue;
            }

            $persons = 0;

            /** @var CustomerBooking $booking */
            foreach ($event->getBookings()->getItems() as $booking) {
                if ($booking->getStatus()->getValue() === BookingStatus::APPROVED) {
                    $persons += $booking->getPersons()->getValue();
                }
            }

            if (($isFrontEnd || $isCabinetPage) && $settingsDS->getSetting('general', 'showClientTimeZone')) {
                /** @var EventPeriod $period */
                foreach ($event->getPeriods()->getItems() as $period) {
                    $period->getPeriodStart()->getValue()->setTimezone(new DateTimeZone('UTC'));
                    $period->getPeriodEnd()->getValue()->setTimezone(new DateTimeZone('UTC'));
                }
            }

            $bookingOpens = $event->getBookingOpens() ?
                $event->getBookingOpens()->getValue() : $event->getCreated()->getValue();

            $bookingCloses = $event->getBookingCloses() ?
                $event->getBookingCloses()->getValue() : $event->getPeriods()->getItem(0)->getPeriodStart()->getValue();

            $minimumCancelTimeInSeconds = $settingsDS
                ->getEntitySettings($event->getSettings())
                ->getGeneralSettings()
                ->getMinimumTimeRequirementPriorToCanceling();

            $minimumCancelTime = DateTimeService::getCustomDateTimeObject(
                $event->getPeriods()->getItem(0)->getPeriodStart()->getValue()->format('Y-m-d H:i:s')
            )->modify("-{$minimumCancelTimeInSeconds} seconds");

            $eventsInfo = [
                'bookable'   => $reservationService->isBookable($event, null, $currentDateTime),
                'cancelable' => $currentDateTime <= $minimumCancelTime,
                'opened'     => ($currentDateTime > $bookingOpens) && ($currentDateTime < $bookingCloses),
                'closed'     => $currentDateTime > $bookingCloses,
                'places'     => $event->getMaxCapacity()->getValue() - $persons
            ];

            if ($isFrontEnd) {
                $event->setBookings(new Collection());
            }

            $ameliaUserId = $userAS->isAmeliaUser($user) && $user->getId() ? $user->getId()->getValue() : null;

            // Delete other bookings if user is customer
            if ($userAS->isCustomer($user)) {
                /** @var CustomerBooking $booking */
                foreach ($event->getBookings()->getItems() as $bookingKey => $booking) {
                    if ($booking->getCustomerId()->getValue() !== $ameliaUserId) {
                        $event->getBookings()->deleteItem($bookingKey);
                    }
                }
            }

            if (!$isFrontEnd && $userAS->isCustomer($user) && $event->getBookings()->length() === 0) {
                continue;
            }

            $eventsArray[] = array_merge($event->toArray(), $eventsInfo);
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully retrieved events');
        $result->setData([
            Entities::EVENTS => $eventsArray,
            'count'          => (int)$eventRepository->getFilteredIdsCount($params)
        ]);

        return $result;
    }
}
