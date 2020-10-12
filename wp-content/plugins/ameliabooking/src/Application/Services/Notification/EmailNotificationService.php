<?php
/**
 * @copyright © TMS-Plugins. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Services\Notification;

use AmeliaBooking\Application\Services\Helper\HelperService;
use AmeliaBooking\Application\Services\Placeholder\PlaceholderService;
use AmeliaBooking\Application\Services\Settings\SettingsService;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\Notification\Notification;
use AmeliaBooking\Domain\Entity\User\Customer;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\ValueObjects\String\NotificationStatus;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\CustomerBookingRepository;
use AmeliaBooking\Infrastructure\Repository\Notification\NotificationLogRepository;
use AmeliaBooking\Infrastructure\Repository\User\UserRepository;
use AmeliaBooking\Infrastructure\Services\Notification\MailgunService;
use AmeliaBooking\Infrastructure\Services\Notification\PHPMailService;
use AmeliaBooking\Infrastructure\Services\Notification\SMTPService;
use InvalidArgumentException;
use Slim\Exception\ContainerException;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class EmailNotificationService
 *
 * @package AmeliaBooking\Application\Services\Notification
 */
class EmailNotificationService extends AbstractNotificationService
{
    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * @param array        $appointmentArray
     * @param Notification $notification
     * @param bool         $logNotification
     *
     * @param int|null     $bookingKey
     *
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     * @throws ContainerValueNotFoundException
     * @throws \AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException
     * @throws \AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     * @throws \Exception
     */
    public function sendNotification(
        $appointmentArray,
        $notification,
        $logNotification,
        $bookingKey = null
    ) {
        /** @var NotificationLogRepository $notificationLogRepo */
        $notificationLogRepo = $this->container->get('domain.notificationLog.repository');
        /** @var UserRepository $userRepository */
        $userRepository = $this->container->get('domain.users.repository');
        /** @var CustomerBookingRepository $bookingRepository */
        $bookingRepository = $this->container->get('domain.booking.customerBooking.repository');

        $token = isset($appointmentArray['bookings'][$bookingKey]) ?
            $bookingRepository->getToken($appointmentArray['bookings'][$bookingKey]['id']) : null;

        /** @var PHPMailService|SMTPService|MailgunService $mailService */
        $mailService = $this->container->get('infrastructure.mail.service');
        /** @var PlaceholderService $placeholderService */
        $placeholderService = $this->container->get("application.placeholder.{$appointmentArray['type']}.service");
        /** @var SettingsService $settingsAS */
        $settingsAS = $this->container->get('application.settings.service');

        $data = $placeholderService->getPlaceholdersData(
            $appointmentArray,
            $bookingKey,
            isset($token['token']) ? $token['token'] : null,
            'email'
        );

        $subject = $placeholderService->applyPlaceholders($notification->getSubject()->getValue(), $data);

        $body = $placeholderService->applyPlaceholders($notification->getContent()->getValue(), $data);

        $users = $this->getUsersInfo(
            $notification->getSendTo()->getValue(),
            $appointmentArray,
            $bookingKey,
            $data
        );

        foreach ($users as $user) {
            try {
                if ($user['email']) {
                    $mailService->send(
                        $user['email'],
                        $subject,
                        $this->getParsedBody($body),
                        $settingsAS->getBccEmails()
                    );

                    if ($logNotification) {
                        $notificationLogRepo->add(
                            $notification,
                            $userRepository->getById($user['id']),
                            $appointmentArray['type'] === Entities::APPOINTMENT ? $appointmentArray['id'] : null,
                            $appointmentArray['type'] === Entities::EVENT ? $appointmentArray['id'] : null
                        );
                    }
                }
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * @throws ContainerValueNotFoundException
     * @throws QueryExecutionException
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     * @throws \Interop\Container\Exception\ContainerException
     * @throws \Exception
     */
    public function sendBirthdayGreetingNotifications()
    {
        /** @var Notification $notification */
        $notification = $this->getByNameAndType('customer_birthday_greeting', $this->type);

        // Check if notification is enabled and it is time to send notification
        if ($notification->getStatus()->getValue() === NotificationStatus::ENABLED &&
            DateTimeService::getNowDateTimeObject() >=
            DateTimeService::getCustomDateTimeObject($notification->getTime()->getValue())
        ) {
            /** @var NotificationLogRepository $notificationLogRepo */
            $notificationLogRepo = $this->container->get('domain.notificationLog.repository');

            /** @var PHPMailService|SMTPService|MailgunService $mailService */
            $mailService = $this->container->get('infrastructure.mail.service');

            /** @var PlaceholderService $placeholderService */
            $placeholderService = $this->container->get('application.placeholder.appointment.service');

            /** @var SettingsService $settingsAS */
            $settingsAS = $this->container->get('application.settings.service');

            $customers = $notificationLogRepo->getBirthdayCustomers($this->type);
            $companyData = $placeholderService->getCompanyData();
            $customersArray = $customers->toArray();

            foreach ($customersArray as $bookingKey => $customerArray) {
                if ($customerArray['email']) {
                    $data = [
                        'customer_email'      => $customerArray['email'],
                        'customer_first_name' => $customerArray['firstName'],
                        'customer_last_name'  => $customerArray['lastName'],
                        'customer_full_name'  => $customerArray['firstName'] . ' ' . $customerArray['lastName'],
                        'customer_phone'      => $customerArray['phone']
                    ];

                    /** @noinspection AdditionOperationOnArraysInspection */
                    $data += $companyData;

                    $subject = $placeholderService->applyPlaceholders(
                        $notification->getSubject()->getValue(),
                        $data
                    );

                    $body = $placeholderService->applyPlaceholders(
                        $notification->getContent()->getValue(),
                        $data
                    );

                    try {
                        $mailService->send(
                            $data['customer_email'],
                            $subject,
                            $this->getParsedBody($body),
                            $settingsAS->getBccEmails()
                        );

                        $notificationLogRepo->add(
                            $notification,
                            $customers->getItem($bookingKey)
                        );
                    } catch (\Exception $e) {
                    }
                }
            }
        }
    }

    /**
     * @param Customer $customer
     *
     * @return void
     *
     * @throws ContainerValueNotFoundException
     * @throws ContainerException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     * @throws \Exception
     */
    public function sendRecoveryEmail($customer)
    {
        /** @var Notification $notification */
        $notification = $this->getByNameAndType('customer_account_recovery', 'email');

        /** @var PHPMailService|SMTPService|MailgunService $mailService */
        $mailService = $this->container->get('infrastructure.mail.service');

        /** @var PlaceholderService $placeholderService */
        $placeholderService = $this->container->get('application.placeholder.appointment.service');

        /** @var HelperService $helperService */
        $helperService = $this->container->get('application.helper.service');

        if ($notification->getStatus()->getValue() === NotificationStatus::ENABLED) {
            $data = [
                'customer_email'      => $customer->getEmail()->getValue(),
                'customer_first_name' => $customer->getFirstName()->getValue(),
                'customer_last_name'  => $customer->getLastName()->getValue(),
                'customer_full_name'  =>
                    $customer->getFirstName()->getValue() . ' ' . $customer->getLastName()->getValue(),
                'customer_phone'      => $customer->getPhone() ? $customer->getPhone()->getValue() : '',
                'customer_panel_url'  => $helperService->getCustomerCabinetUrl(
                    $customer->getEmail()->getValue(),
                    'email',
                    null,
                    null
                )
            ];

            /** @noinspection AdditionOperationOnArraysInspection */
            $data += $placeholderService->getCompanyData();

            $subject = $placeholderService->applyPlaceholders(
                $notification->getSubject()->getValue(),
                $data
            );

            $body = $placeholderService->applyPlaceholders(
                $notification->getContent()->getValue(),
                $data
            );

            try {
                $mailService->send($data['customer_email'], $subject, $this->getParsedBody($body), false);
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * @param Provider $provider
     *
     * @return void
     *
     * @throws ContainerValueNotFoundException
     * @throws ContainerException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     * @throws \Exception
     */
    public function sendEmployeePanelAccess($provider, $plainPassword)
    {
        /** @var Notification $notification */
        $notification = $this->getByNameAndType('provider_panel_access', 'email');

        /** @var PHPMailService|SMTPService|MailgunService $mailService */
        $mailService = $this->container->get('infrastructure.mail.service');

        /** @var PlaceholderService $placeholderService */
        $placeholderService = $this->container->get('application.placeholder.appointment.service');

        if ($notification->getStatus()->getValue() === NotificationStatus::ENABLED) {
            $data = [
                'employee_email'      => $provider['email'],
                'employee_first_name' => $provider['firstName'],
                'employee_last_name'  => $provider['lastName'],
                'employee_full_name'  =>
                    $provider['firstName'] . ' ' . $provider['lastName'],
                'employee_phone'      => $provider['phone'],
                'employee_password'   => $plainPassword,
                'employee_panel_url'  => trim($this->container->get('domain.settings.service')
                    ->getSetting('roles', 'providerCabinet')['pageUrl'])
            ];

            /** @noinspection AdditionOperationOnArraysInspection */
            $data += $placeholderService->getCompanyData();

            $subject = $placeholderService->applyPlaceholders(
                $notification->getSubject()->getValue(),
                $data
            );

            $body = $placeholderService->applyPlaceholders(
                $notification->getContent()->getValue(),
                $data
            );

            try {
                $mailService->send($data['employee_email'], $subject, $this->getParsedBody($body), false);
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * @param string $body
     *
     * @return string
     *
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function getParsedBody($body)
    {
        /** @var \AmeliaBooking\Domain\Services\Settings\SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $body = str_replace('class="ql-size-small"', 'style="font-size: 0.75em;"', $body);
        $body = str_replace('class="ql-size-large"', 'style="font-size: 1.5em;"', $body);
        $body = str_replace('class="ql-size-huge"', 'style="font-size: 2.5em;"', $body);

        $breakReplacement = $settingsService->getSetting('notifications', 'breakReplacement');

        return !$breakReplacement ? $body : str_replace('<p><br></p>', $breakReplacement, $body);
    }
}
