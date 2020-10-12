<?php

namespace AmeliaBooking\Application\Services\TimeSlot;

use AmeliaBooking\Application\Services\Bookable\BookableApplicationService;
use AmeliaBooking\Application\Services\Booking\AppointmentApplicationService;
use AmeliaBooking\Application\Services\Booking\EventApplicationService;
use AmeliaBooking\Application\Services\User\ProviderApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Bookable\Service\Service;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\ServiceRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;
use AmeliaBooking\Infrastructure\Repository\Location\LocationRepository;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\Services\Google\GoogleCalendarService;
use AmeliaBooking\Infrastructure\Services\Outlook\OutlookCalendarService;

/**
 * Class TimeSlotService
 *
 * @package AmeliaBooking\Application\Services\TimeSlot
 */
class TimeSlotService
{
    private $container;

    /**
     * TimeSlotService constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * @param Service   $service
     * @param int       $locationId
     * @param \DateTime $startDateTime
     * @param \DateTime $endDateTime
     * @param array     $providerIds
     * @param array     $selectedExtras
     * @param int       $excludeAppointmentId
     * @param int       $personsCount
     * @param int       $isFrontEndBooking
     *
     * @return array
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws \Exception
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function getFreeSlots(
        $service,
        $locationId,
        $startDateTime,
        $endDateTime,
        $providerIds,
        $selectedExtras,
        $excludeAppointmentId,
        $personsCount,
        $isFrontEndBooking
    ) {
        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');
        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');
        /** @var \AmeliaBooking\Domain\Services\TimeSlot\TimeSlotService $timeSlotService */
        $timeSlotService = $this->container->get('domain.timeSlot.service');
        /** @var \AmeliaBooking\Domain\Services\Settings\SettingsService $settingsDomainService */
        $settingsDomainService = $this->container->get('domain.settings.service');
        /** @var \AmeliaBooking\Application\Services\Settings\SettingsService $settingsApplicationService */
        $settingsApplicationService = $this->container->get('application.settings.service');
        /** @var BookableApplicationService $bookableApplicationService */
        $bookableApplicationService = $this->container->get('application.bookable.service');
        /** @var AppointmentApplicationService $appointmentApplicationService */
        $appointmentApplicationService = $this->container->get('application.booking.appointment.service');
        /** @var ProviderApplicationService $providerApplicationService */
        $providerApplicationService = $this->container->get('application.user.provider.service');
        /** @var GoogleCalendarService $googleCalendarService */
        $googleCalendarService = $this->container->get('infrastructure.google.calendar.service');
        /** @var OutlookCalendarService $outlookCalendarService */
        $outlookCalendarService = $this->container->get('infrastructure.outlook.calendar.service');

        $bookableApplicationService->checkServiceTimes($service);

        /** @var Collection $extras */
        $extras = $bookableApplicationService->filterServiceExtras(array_column($selectedExtras, 'id'), $service);

        $isGloballyBusySlot = $settingsDomainService->getSetting('appointments', 'isGloballyBusySlot');

        /** @var Collection $futureAppointments */
        $futureAppointments = $appointmentRepository->getFutureAppointments(
            $isGloballyBusySlot ? [] : $providerIds,
            $excludeAppointmentId,
            [BookingStatus::APPROVED, BookingStatus::PENDING]
        );

        /** @var Collection $providers */
        $providers = $providerRepository->getByCriteria([
            'services'  => [$service->getId()->getValue()],
            'providers' => $providerIds
        ]);

        /** @var LocationRepository $locationRepository */
        $locationRepository = $this->container->get('domain.locations.repository');

        /** @var Collection $locationsList */
        $locationsList = $locationRepository->getAllOrderedByName();

        /** @var EventApplicationService $eventApplicationService */
        $eventApplicationService = $this->container->get('application.booking.event.service');

        $eventApplicationService->removeSlotsFromEvents($providers, [
            DateTimeService::getCustomDateTimeObject($startDateTime->format('Y-m-d H:i:s'))
                ->modify('-10 day')
                ->format('Y-m-d H:i:s'),
            DateTimeService::getCustomDateTimeObject($startDateTime->format('Y-m-d H:i:s'))
                ->modify('+2 years')
                ->format('Y-m-d H:i:s')
        ]);

        try {
            // Remove Google Calendar Busy Slots
            $googleCalendarService->removeSlotsFromGoogleCalendar($providers, $excludeAppointmentId);
        } catch (\Exception $e) {
        }

        try {
            // Remove Outlook Calendar Busy Slots
            $outlookCalendarService->removeSlotsFromOutlookCalendar($providers, $excludeAppointmentId);
        } catch (\Exception $e) {
        }

        $providerApplicationService->addAppointmentsToAppointmentList($providers, $futureAppointments);

        $freeIntervals = $timeSlotService->getFreeTime(
            $service,
            $locationId,
            $locationsList,
            $providers,
            $settingsApplicationService->getGlobalDaysOff(),
            $startDateTime,
            $endDateTime,
            $personsCount,
            $settingsDomainService->getSetting('appointments', 'allowBookingIfPending'),
            $settingsDomainService->getSetting('appointments', 'allowBookingIfNotMin'),
            $isFrontEndBooking ? $settingsDomainService->getSetting('appointments', 'openedBookingAfterMin') : false
        );

        // Find slot length and required appointment time
        $requiredTime = $appointmentApplicationService->getAppointmentRequiredTime($service, $extras, $selectedExtras);

        // Get free slots for providers
        return $timeSlotService->getAppointmentFreeSlots(
            $service,
            $requiredTime,
            $freeIntervals,
            $settingsDomainService->getSetting('general', 'timeSlotLength') ?: $requiredTime,
            $startDateTime,
            $settingsDomainService->getSetting('general', 'serviceDurationAsSlot'),
            $settingsDomainService->getSetting('general', 'bufferTimeInSlot'),
            true
        );
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * @param int       $serviceId
     * @param \DateTime $requiredDateTime
     * @param int       $providerId
     * @param array     $selectedExtras
     * @param int       $excludeAppointmentId
     * @param int       $personsCount
     * @param boolean   $isFrontEndBooking
     *
     * @return boolean
     * @throws \AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     * @throws \Interop\Container\Exception\ContainerException
     * @throws \Exception
     */
    public function isSlotFree(
        $serviceId,
        $requiredDateTime,
        $providerId,
        $selectedExtras,
        $excludeAppointmentId,
        $personsCount,
        $isFrontEndBooking
    ) {
        $dateKey = $requiredDateTime->format('Y-m-d');
        $timeKey = $requiredDateTime->format('H:i');

        /** @var SettingsService $settingsDS */
        $settingsDS = $this->container->get('domain.settings.service');
        /** @var ServiceRepository $serviceRepository */
        $serviceRepository = $this->container->get('domain.bookable.service.repository');

        /** @var Service $service */
        $service = $serviceRepository->getByIdWithExtras($serviceId);

        $minimumBookingTimeInSeconds = $settingsDS
            ->getEntitySettings($service->getSettings())
            ->getGeneralSettings()
            ->getMinimumTimeRequirementPriorToBooking();

        $maximumBookingTimeInDays = $settingsDS
            ->getEntitySettings($service->getSettings())
            ->getGeneralSettings()
            ->getNumberOfDaysAvailableForBooking();

        $freeSlots = $this->getFreeSlots(
            $service,
            null,
            $this->getMinimumDateTimeForBooking(
                '',
                $isFrontEndBooking,
                $minimumBookingTimeInSeconds
            ),
            $this->getMaximumDateTimeForBooking(
                '',
                $isFrontEndBooking,
                $maximumBookingTimeInDays
            ),
            [$providerId],
            $selectedExtras,
            $excludeAppointmentId,
            $personsCount,
            $isFrontEndBooking
        );

        return array_key_exists($dateKey, $freeSlots) && array_key_exists($timeKey, $freeSlots[$dateKey]);
    }

    /**
     * @param string  $requiredBookingDateTimeString
     * @param boolean $isFrontEndBooking
     * @param string  $minimumTime
     *
     * @return \DateTime
     * @throws \Exception
     */
    public function getMinimumDateTimeForBooking($requiredBookingDateTimeString, $isFrontEndBooking, $minimumTime)
    {
        $requiredTimeOffset = $isFrontEndBooking ? $minimumTime : 0;

        $minimumBookingDateTime = DateTimeService::getNowDateTimeObject()->modify("+{$requiredTimeOffset} seconds");

        $requiredBookingDateTime = DateTimeService::getCustomDateTimeObject($requiredBookingDateTimeString);

        $minimumDateTime = ($minimumBookingDateTime > $requiredBookingDateTime ||
            $minimumBookingDateTime->format('Y-m-d') === $requiredBookingDateTime->format('Y-m-d')
        ) ? $minimumBookingDateTime : $requiredBookingDateTime->setTime(0, 0, 0);

        if (!$isFrontEndBooking) {
            $minimumDateTime->modify('-365 days');
        }

        return $minimumDateTime;
    }

    /**
     * @param string  $requiredBookingDateTimeString
     * @param boolean $isFrontEndBooking
     * @param int     $maximumTime
     *
     * @return \DateTime
     * @throws \Exception
     */
    public function getMaximumDateTimeForBooking($requiredBookingDateTimeString, $isFrontEndBooking, $maximumTime)
    {
        $days = $maximumTime > SettingsService::NUMBER_OF_DAYS_AVAILABLE_FOR_BOOKING ?
            $maximumTime :
            SettingsService::NUMBER_OF_DAYS_AVAILABLE_FOR_BOOKING;

        $daysAvailableForBooking = $isFrontEndBooking ? $maximumTime : $days;

        $maximumBookingDateTime = DateTimeService::getNowDateTimeObject()->modify("+{$daysAvailableForBooking} day");

        $requiredBookingDateTime = $requiredBookingDateTimeString ?
            DateTimeService::getCustomDateTimeObject($requiredBookingDateTimeString) : $maximumBookingDateTime;

        return ($maximumBookingDateTime < $requiredBookingDateTime ||
            $maximumBookingDateTime->format('Y-m-d') === $requiredBookingDateTime->format('Y-m-d')
        ) ? $maximumBookingDateTime : $requiredBookingDateTime;
    }
}
