<?php
/**
 * @copyright © TMS-Plugins. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Services\Placeholder;

use AmeliaBooking\Application\Services\Helper\HelperService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Entity\Bookable\Service\Category;
use AmeliaBooking\Domain\Entity\Bookable\Service\Extra;
use AmeliaBooking\Domain\Entity\Bookable\Service\Service;
use AmeliaBooking\Domain\Entity\Location\Location;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\CategoryRepository;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\ExtraRepository;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\ServiceRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\CustomerBookingRepository;
use AmeliaBooking\Infrastructure\Repository\Location\LocationRepository;
use AmeliaBooking\Infrastructure\Repository\User\UserRepository;
use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;
use DateTime;

/**
 * Class AppointmentPlaceholderService
 *
 * @package AmeliaBooking\Application\Services\Notification
 */
class AppointmentPlaceholderService extends PlaceholderService
{
    /**
     *
     * @return array
     *
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function getEntityPlaceholdersDummyData()
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var HelperService $helperService */
        $helperService = $this->container->get('application.helper.service');

        $companySettings = $settingsService->getCategorySettings('company');

        $dateFormat = $settingsService->getSetting('wordpress', 'dateFormat');
        $timeFormat = $settingsService->getSetting('wordpress', 'timeFormat');

        $timestamp = date_create()->getTimestamp();

        return [
            'appointment_date'        => date_i18n($dateFormat, strtotime($timestamp)),
            'appointment_date_time'   => date_i18n($dateFormat . ' ' . $timeFormat, strtotime($timestamp)),
            'appointment_start_time'  => date_i18n($timeFormat, $timestamp),
            'appointment_end_time'    => date_i18n($timeFormat, date_create('1 hour')->getTimestamp()),
            'appointment_notes'       => 'Appointment note',
            'appointment_price'       => $helperService->getFormattedPrice(100),
            'employee_email'          => 'employee@domain.com',
            'employee_first_name'     => 'Richard',
            'employee_last_name'      => 'Roe',
            'employee_full_name'      => 'Richard Roe',
            'employee_phone'          => '150-698-1858',
            'employee_note'           => 'Employee Note',
            'location_address'        => $companySettings['address'],
            'location_phone'          => $companySettings['phone'],
            'location_name'           => 'Location Name',
            'location_description'    => 'Location Description',
            'category_name'           => 'Category Name',
            'service_description'     => 'Service Description',
            'reservation_description' => 'Service Description',
            'service_duration'        => $helperService->secondsToNiceDuration(5400),
            'service_name'            => 'Service Name',
            'reservation_name'        => 'Service Name',
            'service_price'           => $helperService->getFormattedPrice(100)
        ];
    }

    /**
     * @param array  $appointment
     * @param int    $bookingKey
     * @param string $token
     * @param string $type
     *
     * @return array
     *
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     * @throws \Exception
     */
    public function getEntityPlaceholdersData($appointment, $bookingKey = null, $token = null, $type = null)
    {
        $data = [];

        $data = array_merge($data, $this->getAppointmentData($appointment, $bookingKey, $type));
        $data = array_merge($data, $this->getServiceData($appointment, $bookingKey));
        $data = array_merge($data, $this->getEmployeeData($appointment));
        $data = array_merge($data, $this->getRecurringAppointmentsData($appointment, $bookingKey, $type));

        return $data;
    }

    /**
     * @param        $appointment
     * @param null   $bookingKey
     * @param string $type
     *
     * @return array
     *
     * @throws \Interop\Container\Exception\ContainerException
     * @throws \Exception
     */
    private function getAppointmentData($appointment, $bookingKey = null, $type = null)
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $dateFormat = $settingsService->getSetting('wordpress', 'dateFormat');
        $timeFormat = $settingsService->getSetting('wordpress', 'timeFormat');

        if ($bookingKey !== null && $appointment['bookings'][$bookingKey]['utcOffset'] !== null
            && $settingsService->getSetting('general', 'showClientTimeZone')) {
            $bookingStart = DateTimeService::getClientUtcCustomDateTimeObject(
                DateTimeService::getCustomDateTimeInUtc($appointment['bookingStart']),
                $appointment['bookings'][$bookingKey]['utcOffset']
            );

            $bookingEnd = DateTimeService::getClientUtcCustomDateTimeObject(
                DateTimeService::getCustomDateTimeInUtc($appointment['bookingEnd']),
                $appointment['bookings'][$bookingKey]['utcOffset']
            );
        } else {
            $bookingStart = DateTime::createFromFormat('Y-m-d H:i:s', $appointment['bookingStart']);
            $bookingEnd = DateTime::createFromFormat('Y-m-d H:i:s', $appointment['bookingEnd']);
        }

        $zoomStartUrl = '';
        $zoomJoinUrl = '';

        if (isset($appointment['zoomMeeting']['joinUrl'], $appointment['zoomMeeting']['startUrl'])) {
            $zoomStartUrl = $appointment['zoomMeeting']['startUrl'];
            $zoomJoinUrl = $appointment['zoomMeeting']['joinUrl'];
        }

        return [
            'appointment_status'     => BackendStrings::getCommonStrings()[$appointment['status']],
            'appointment_notes'      => $appointment['internalNotes'],
            'appointment_date'       => date_i18n($dateFormat, $bookingStart->getTimestamp()),
            'appointment_date_time'  => date_i18n($dateFormat . ' ' . $timeFormat, $bookingStart->getTimestamp()),
            'appointment_start_time' => date_i18n($timeFormat, $bookingStart->getTimestamp()),
            'appointment_end_time'   => date_i18n($timeFormat, $bookingEnd->getTimestamp()),
            'zoom_host_url'          => $zoomStartUrl && $type === 'email' ?
                '<a href="' . $zoomStartUrl . '">' . BackendStrings::getCommonStrings()['zoom_click_to_start'] . '</a>'
                : $zoomStartUrl,
            'zoom_join_url'          => $zoomJoinUrl && $type === 'email' ?
                '<a href="' . $zoomJoinUrl . '">' . BackendStrings::getCommonStrings()['zoom_click_to_join'] . '</a>'
                : $zoomJoinUrl,
        ];
    }

    /**
     * @param $appointmentArray
     * @param $bookingKey
     *
     * @return array
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     */
    private function getServiceData($appointmentArray, $bookingKey = null)
    {
        /** @var CategoryRepository $categoryRepository */
        $categoryRepository = $this->container->get('domain.bookable.category.repository');
        /** @var ServiceRepository $serviceRepository */
        $serviceRepository = $this->container->get('domain.bookable.service.repository');

        /** @var HelperService $helperService */
        $helperService = $this->container->get('application.helper.service');

        /** @var Service $service */
        $service = $serviceRepository->getByIdWithExtras($appointmentArray['serviceId']);
        /** @var Category $category */
        $category = $categoryRepository->getById($service->getCategoryId()->getValue());

        $data = [
            'category_name'           => $category->getName()->getValue(),
            'service_description'     => $service->getDescription()->getValue(),
            'reservation_description' => $service->getDescription()->getValue(),
            'service_duration'        => $helperService->secondsToNiceDuration($service->getDuration()->getValue()),
            'service_name'            => $service->getName()->getValue(),
            'reservation_name'        => $service->getName()->getValue(),
            'service_price'           => $helperService->getFormattedPrice($service->getPrice()->getValue())
        ];

        $bookingExtras = [];

        foreach ((array)$appointmentArray['bookings'] as $booking) {
            foreach ((array)$booking['extras'] as $bookingExtra) {
                $bookingExtras[$bookingExtra['extraId']] = [
                    'quantity' => $bookingExtra['quantity']
                ];
            }
        }

        /** @var ExtraRepository $extraRepository */
        $extraRepository = $this->container->get('domain.bookable.extra.repository');

        /** @var Collection $extras */
        $extras = $extraRepository->getAllIndexedById();

        $duration = $service->getDuration()->getValue();

        if ($bookingKey !== null) {
            foreach ($appointmentArray['bookings'][$bookingKey]['extras'] as $bookingExtra) {
                /** @var Extra $extra */
                $extra = $extras->getItem($bookingExtra['extraId']);

                $duration += $extra->getDuration() ? $extra->getDuration()->getValue() * $bookingExtra['quantity'] : 0;
            }
        } else {
            $maxBookingDuration = 0;

            foreach ($appointmentArray['bookings'] as $booking) {
                $bookingDuration = $duration;

                foreach ($booking['extras'] as $bookingExtra) {
                    /** @var Extra $extra */
                    $extra = $extras->getItem($bookingExtra['extraId']);

                    $bookingDuration += $extra->getDuration() ?
                        $extra->getDuration()->getValue() * $bookingExtra['quantity'] : 0;
                }

                if ($bookingDuration > $maxBookingDuration &&
                    ($booking['status'] === BookingStatus::APPROVED || $booking['status'] === BookingStatus::PENDING)
                ) {
                    $maxBookingDuration = $bookingDuration;
                }
            }

            $duration = $maxBookingDuration;
        }

        $data['appointment_duration'] = $helperService->secondsToNiceDuration($duration);

        /** @var Extra $extra */
        foreach ($extras->getItems() as $extra) {
            $extraId = $extra->getId()->getValue();

            $data["service_extra_{$extraId}_name"] =
                array_key_exists($extraId, $bookingExtras) ? $extra->getName()->getValue() : '';

            $data["service_extra_{$extraId}_quantity"] =
                array_key_exists($extraId, $bookingExtras) ? $bookingExtras[$extraId]['quantity'] : '';

            $data["service_extra_{$extraId}_price"] = array_key_exists($extraId, $bookingExtras) ?
                $helperService->getFormattedPrice($extra->getPrice()->getValue()) : '';
        }

        return $data;
    }

    /**
     * @param $appointment
     *
     * @return array
     *
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     */
    private function getEmployeeData($appointment)
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->container->get('domain.users.repository');
        /** @var LocationRepository $locationRepository */
        $locationRepository = $this->container->get('domain.locations.repository');

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var Provider $user */
        $user = $userRepository->getById($appointment['providerId']);

        if (!($locationId = $appointment['locationId'])) {
            $locationId = $user->getLocationId() ? $user->getLocationId()->getValue() : null;
        }

        /** @var Location $location */
        $location = $locationId ? $locationRepository->getById($locationId) : null;

        return [
            'employee_email'       => $user->getEmail()->getValue(),
            'employee_first_name'  => $user->getFirstName()->getValue(),
            'employee_last_name'   => $user->getLastName()->getValue(),
            'employee_full_name'   => $user->getFirstName()->getValue() . ' ' . $user->getLastName()->getValue(),
            'employee_phone'       => $user->getPhone()->getValue(),
            'employee_note'        => $user->getNote() ? $user->getNote()->getValue() : '',
            'employee_panel_url'  => trim($this->container->get('domain.settings.service')
                ->getSetting('roles', 'providerCabinet')['pageUrl']),
            'location_address'     => !$location ?
                $settingsService->getSetting('company', 'address') : $location->getAddress()->getValue(),
            'location_phone'       => !$location ?
                $settingsService->getSetting('company', 'phone') : $location->getPhone()->getValue(),
            'location_name'        => !$location ?
                $settingsService->getSetting('company', 'address') : $location->getName()->getValue(),
            'location_description' => $location && $location->getDescription() ?
                $location->getDescription()->getValue() : ''
        ];
    }

    /**
     * @param array  $appointment
     * @param int    $bookingKey
     * @param string $type
     *
     * @return array
     *
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function getRecurringAppointmentsData($appointment, $bookingKey, $type)
    {
        if (!array_key_exists('recurring', $appointment)) {
            return [
                'recurring_appointments_details' => ''
            ];
        }

        /** @var CustomerBookingRepository $bookingRepository */
        $bookingRepository = $this->container->get('domain.booking.customerBooking.repository');

        /** @var PlaceholderService $placeholderService */
        $placeholderService = $this->container->get("application.placeholder.appointment.service");

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $appointmentsSettings = $settingsService->getCategorySettings('appointments');

        $recurringAppointmentDetails = [];

        foreach ($appointment['recurring'] as $recurringData) {
            $recurringBookingKey = null;

            if ($bookingKey !== null) {
                foreach ($recurringData['appointment']['bookings'] as $key => $recurringBooking) {
                    if (isset($recurringData['booking']['id'])) {
                        if ($recurringBooking['id'] === $recurringData['booking']['id']) {
                            $recurringBookingKey = $key;
                        }
                    } else {
                        $recurringBookingKey = $bookingKey;
                    }
                }
            }

            $token = isset($recurringData['appointment']['bookings'][$bookingKey]) ?
                $bookingRepository->getToken($recurringData['appointment']['bookings'][$bookingKey]['id']) : null;

            $recurringPlaceholders = array_merge(
                $this->getEmployeeData($recurringData['appointment']),
                $this->getAppointmentData($recurringData['appointment'], $recurringBookingKey, $type),
                $this->getBookingData(
                    $recurringData['appointment'],
                    $type,
                    $recurringBookingKey,
                    isset($token['token']) ? $token['token'] : null
                )
            );

            if ($bookingKey === null) {
                if (isset($recurringPlaceholders['appointment_cancel_url'])) {
                    $recurringPlaceholders['appointment_cancel_url'] = '';
                }

                $recurringPlaceholders['zoom_join_url'] = '';
            } else {
                $recurringPlaceholders['zoom_host_url'] = '';
            }

            if (!empty($recurringPlaceholders['appointment_cancel_url'])) {
                $recurringPlaceholders['appointment_cancel_url'] = $type === 'email' ?
                    '<a href="' . $recurringPlaceholders['appointment_cancel_url'] . '">' . BackendStrings::getAppointmentStrings()['cancel_appointment'] . '</a>'
                    : $recurringPlaceholders['appointment_cancel_url'];
            }

            $recurringAppointmentDetails[] = $placeholderService->applyPlaceholders(
                $appointmentsSettings['recurringPlaceholders'],
                $recurringPlaceholders
            );
        }

        return [
            'recurring_appointments_details' => $recurringAppointmentDetails ? implode(
                $type === 'email' ? '<br>' : PHP_EOL,
                $recurringAppointmentDetails
            ) : ''
        ];
    }
}
