<?php
/**
 * Settings hook for activation
 */

namespace AmeliaBooking\Infrastructure\WP\InstallActions;

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\String\Token;
use AmeliaBooking\Infrastructure\Services\Frontend\LessParserService;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;
use Exception;

/**
 * Class ActivationSettingsHook
 *
 * @package AmeliaBooking\Infrastructure\WP\InstallActions
 */
class ActivationSettingsHook
{
    /**
     * Initialize the plugin
     *
     * @throws Exception
     */
    public static function init()
    {
        self::initDBSettings();

        self::initGeneralSettings();

        self::initCompanySettings();

        self::initNotificationsSettings();

        self::initDaysOffSettings();

        self::initWeekScheduleSettings();

        self::initGoogleCalendarSettings();

        self::initOutlookCalendarSettings();

        self::initPaymentsSettings();

        self::initActivationSettings();

        self::initCustomizationSettings();

        self::initLabelsSettings();

        self::initRolesSettings();

        self::initAppointmentsSettings();

        self::initWebHooksSettings();

        self::initZoomSettings();
    }

    /**
     * @param string $category
     * @param array  $settings
     * @param bool   $replace
     */
    public static function initSettings($category, $settings, $replace = false)
    {
        $settingsService = new SettingsService(new SettingsStorage());

        if (!$settingsService->getCategorySettings($category)) {
            $settingsService->setCategorySettings(
                $category,
                []
            );
        }

        foreach ($settings as $key => $value) {
            if ($replace || null === $settingsService->getSetting($category, $key)) {
                $settingsService->setSetting(
                    $category,
                    $key,
                    $value
                );
            }
        }
    }

    /**
     * Init General Settings
     */
    private static function initGeneralSettings()
    {
        $settings = [
            'timeSlotLength'                         => 1800,
            'serviceDurationAsSlot'                  => false,
            'bufferTimeInSlot'                       => true,
            'defaultAppointmentStatus'               => 'approved',
            'minimumTimeRequirementPriorToBooking'   => 0,
            'minimumTimeRequirementPriorToCanceling' => 0,
            'numberOfDaysAvailableForBooking'        => SettingsService::NUMBER_OF_DAYS_AVAILABLE_FOR_BOOKING,
            'phoneDefaultCountryCode'                => 'auto',
            'requiredPhoneNumberField'               => false,
            'requiredEmailField'                     => true,
            'itemsPerPage'                           => 12,
            'gMapApiKey'                             => '',
            'addToCalendar'                          => true,
            'defaultPageOnBackend'                   => 'Dashboard',
            'showClientTimeZone'                     => false,
            'redirectUrlAfterAppointment'            => '',
            'customFieldsUploadsPath'                => '',
            'useWindowVueInAmelia'                   => true,
            'sortingServices'                        => 'nameAsc',
            'googleRecaptcha'                        => [
                'enabled'   => false,
                'invisible' => true,
                'siteKey'   => '',
                'secret'    => '',
            ],
        ];

        self::initSettings('general', $settings);
    }

    /**
     * Init DB Settings
     */
    private static function initDBSettings()
    {
        $settings = [
            'pdoEmulatePrepares' => false,
        ];

        self::initSettings('db', $settings);
    }

    /**
     * Init Company Settings
     */
    private static function initCompanySettings()
    {

        $settings = [
            'pictureFullPath'  => '',
            'pictureThumbPath' => '',
            'name'             => '',
            'address'          => '',
            'phone'            => '',
            'website'          => ''
        ];

        self::initSettings('company', $settings);
    }

    /**
     * Init Notification Settings
     */
    private static function initNotificationsSettings()
    {
        $settings = [
            'mailService'      => 'php',
            'smtpHost'         => '',
            'smtpPort'         => '',
            'smtpSecure'       => 'ssl',
            'smtpUsername'     => '',
            'smtpPassword'     => '',
            'mailgunApiKey'    => '',
            'mailgunDomain'    => '',
            'senderName'       => '',
            'senderEmail'      => '',
            'notifyCustomers'  => true,
            'smsAlphaSenderId' => 'Amelia',
            'smsSignedIn'      => false,
            'smsApiToken'      => '',
            'bccEmail'         => '',
            'cancelSuccessUrl' => '',
            'cancelErrorUrl'   => '',
            'breakReplacement' => ''
        ];

        self::initSettings('notifications', $settings);
    }

    /**
     * Init Days Off Settings
     */
    private static function initDaysOffSettings()
    {
        self::initSettings('daysOff', []);
    }

    /**
     * Init Work Schedule Settings
     */
    private static function initWeekScheduleSettings()
    {
        self::initSettings('weekSchedule', [
            [
                'day'     => 'Monday',
                'time'    => ['09:00', '17:00'],
                'breaks'  => [],
                'periods' => []
            ],
            [
                'day'     => 'Tuesday',
                'time'    => ['09:00', '17:00'],
                'breaks'  => [],
                'periods' => []
            ],
            [
                'day'     => 'Wednesday',
                'time'    => ['09:00', '17:00'],
                'breaks'  => [],
                'periods' => []
            ],
            [
                'day'     => 'Thursday',
                'time'    => ['09:00', '17:00'],
                'breaks'  => [],
                'periods' => []
            ],
            [
                'day'     => 'Friday',
                'time'    => ['09:00', '17:00'],
                'breaks'  => [],
                'periods' => []
            ],
            [
                'day'     => 'Saturday',
                'time'    => [],
                'breaks'  => [],
                'periods' => []
            ],
            [
                'day'     => 'Sunday',
                'time'    => [],
                'breaks'  => [],
                'periods' => []
            ]
        ]);
    }

    /**
     * Init Google Calendar Settings
     */
    private static function initGoogleCalendarSettings()
    {
        $settings = [
            'clientID'                        => '',
            'clientSecret'                    => '',
            'redirectURI'                     => AMELIA_SITE_URL . '/wp-admin/admin.php?page=wpamelia-employees',
            'showAttendees'                   => false,
            'insertPendingAppointments'       => false,
            'addAttendees'                    => false,
            'sendEventInvitationEmail'        => false,
            'removeGoogleCalendarBusySlots'   => false,
            'maximumNumberOfEventsReturned'   => 50,
            'eventTitle'                      => '%service_name%',
            'eventDescription'                => '',
            'includeBufferTimeGoogleCalendar' => false,
        ];

        self::initSettings('googleCalendar', $settings);
    }

    /**
     * Init Outlook Calendar Settings
     */
    private static function initOutlookCalendarSettings()
    {
        $settings = [
            'clientID'                         => '',
            'clientSecret'                     => '',
            'redirectURI'                      => AMELIA_SITE_URL . '/wp-admin/',
            'insertPendingAppointments'        => false,
            'addAttendees'                     => false,
            'sendEventInvitationEmail'         => false,
            'removeOutlookCalendarBusySlots'   => false,
            'maximumNumberOfEventsReturned'    => 50,
            'eventTitle'                       => '%service_name%',
            'eventDescription'                 => '',
            'includeBufferTimeOutlookCalendar' => false,
        ];

        self::initSettings('outlookCalendar', $settings);
    }

    /**
     * Init Zoom Settings
     */
    private static function initZoomSettings()
    {
        $settings = [
            'enabled'                     => true,
            'apiKey'                      => '',
            'apiSecret'                   => '',
            'meetingTitle'                => '%reservation_name%',
            'meetingAgenda'               => '%reservation_description%',
            'pendingAppointmentsMeetings' => false,
        ];

        self::initSettings('zoom', $settings);
    }

    /**
     * Init Payments Settings
     */
    private static function initPaymentsSettings()
    {
        $settings = [
            'currency'                   => 'USD',
            'symbol'                     => '$',
            'priceSymbolPosition'        => 'before',
            'priceNumberOfDecimals'      => 2,
            'priceSeparator'             => 1,
            'hideCurrencySymbolFrontend' => false,
            'defaultPaymentMethod'       => 'onSite',
            'onSite'                     => true,
            'coupons'                    => true,
            'payPal'                     => [
                'enabled'         => false,
                'sandboxMode'     => false,
                'liveApiClientId' => '',
                'liveApiSecret'   => '',
                'testApiClientId' => '',
                'testApiSecret'   => '',
                'description'     => [
                    'enabled'     => false,
                    'appointment' => '',
                    'event'       => ''
                ],
            ],
            'stripe'                     => [
                'enabled'            => false,
                'testMode'           => false,
                'livePublishableKey' => '',
                'liveSecretKey'      => '',
                'testPublishableKey' => '',
                'testSecretKey'      => '',
                'description'        => [
                    'enabled'     => false,
                    'appointment' => '',
                    'event'       => ''
                ],
                'metaData'           => [
                    'enabled'     => false,
                    'appointment' => null,
                    'event'       => null
                ],
            ],
            'wc'                         => [
                'enabled'      => false,
                'productId'    => '',
                'onSiteIfFree' => false,
                'page'         => 'cart',
                'dashboard'    => true,
            ]
        ];

        self::initSettings('payments', $settings);

        self::setNewSettingsToExistingSettings(
            'payments',
            [
                ['stripe', 'description'],
                ['stripe', 'metaData'],
                ['payPal', 'description'],
                ['wc', 'onSiteIfFree'],
                ['wc', 'page'],
                ['wc', 'dashboard'],
            ],
            $settings
        );
    }

    /**
     * Init Purchase Code Settings
     */
    private static function initActivationSettings()
    {
        $settings = [
            'showActivationSettings' => true,
            'active'                 => false,
            'purchaseCodeStore'      => '',
            'envatoTokenEmail'       => '',
            'version'                => '',
            'deleteTables'           => false,
        ];

        self::initSettings('activation', $settings);
    }

    /**
     * Init Customization Settings
     *
     * @throws Exception
     */
    private static function initCustomizationSettings()
    {
        $settingsService = new SettingsService(new SettingsStorage());

        $settings = $settingsService->getCategorySettings('customization');
        unset($settings['hash']);

        $lessParserService = new LessParserService(
            AMELIA_PATH . '/assets/less/frontend/amelia-booking.less',
            UPLOADS_PATH . '/amelia/css',
            $settingsService
        );

        if (!$settings) {
            $settings = [
                'primaryColor'          => '#1A84EE',
                'primaryGradient1'      => '#1A84EE',
                'primaryGradient2'      => '#0454A2',
                'textColor'             => '#354052',
                'textColorOnBackground' => '#FFFFFF',
                'font'                  => 'Roboto',
                'hash'                  => $lessParserService->generateRandomString()
            ];

            self::initSettings('customization', $settings);
        }

        $lessParserService->compileAndSave([
            'color-accent'      => $settings['primaryColor'],
            'color-gradient1'   => $settings['primaryGradient1'],
            'color-gradient2'   => $settings['primaryGradient2'],
            'color-text-prime'  => $settings['textColor'],
            'color-text-second' => $settings['textColor'],
            'color-white'       => $settings['textColorOnBackground'],
            'roboto'            => $settings['font'],
            'hash'              => $settings['hash']
        ]);
    }

    /**
     * Init Labels Settings
     */
    private static function initLabelsSettings()
    {
        $settings = [
            'enabled'   => true,
            'employee'  => 'employee',
            'employees' => 'employees',
            'service'   => 'service',
            'services'  => 'services'
        ];

        self::initSettings('labels', $settings);
    }

    /**
     * Init Roles Settings
     */
    private static function initRolesSettings()
    {
        $settings = [
            'allowConfigureSchedule'      => false,
            'allowConfigureDaysOff'       => false,
            'allowConfigureSpecialDays'   => false,
            'allowConfigureServices'      => false,
            'allowWriteAppointments'      => false,
            'automaticallyCreateCustomer' => false,
            'inspectCustomerInfo'         => false,
            'allowCustomerReschedule'     => false,
            'allowCustomerDeleteProfile'  => false,
            'allowWriteEvents'            => false,
            'customerCabinet'             => [
                'enabled'         => true,
                'headerJwtSecret' => (new Token(null, 20))->getValue(),
                'urlJwtSecret'    => (new Token(null, 20))->getValue(),
                'tokenValidTime'  => 2592000,
                'pageUrl'         => '',
                'loginEnabled'    => true,
                'filterDate'      => false,
            ],
            'providerCabinet'             => [
                'enabled'         => true,
                'headerJwtSecret' => (new Token(null, 20))->getValue(),
                'urlJwtSecret'    => (new Token(null, 20))->getValue(),
                'tokenValidTime'  => 2592000,
                'pageUrl'         => '',
                'loginEnabled'    => true,
                'filterDate'      => false,
            ],
        ];

        self::initSettings('roles', $settings);

        self::setNewSettingsToExistingSettings(
            'roles',
            [
                ['customerCabinet', 'filterDate'],
            ],
            $settings
        );
    }

    /**
     * Init Appointments Settings
     */
    private static function initAppointmentsSettings()
    {
        $settings = [
            'isGloballyBusySlot'    => false,
            'allowBookingIfPending' => true,
            'allowBookingIfNotMin'  => true,
            'openedBookingAfterMin' => false,
            'recurringPlaceholders' => 'DateTime: %appointment_date_time%',
        ];

        self::initSettings('appointments', $settings);
    }

    /**
     * Init Web Hooks Settings
     */
    private static function initWebHooksSettings()
    {
        $settings = [];

        self::initSettings('webHooks', $settings);
    }

    /**
     * Add new settings ti global parent settings
     *
     * @param string $category
     * @param array  $pathsKeys
     * @param array  $initSettings
     */
    private static function setNewSettingsToExistingSettings($category, $pathsKeys, $initSettings)
    {
        $settingsService = new SettingsService(new SettingsStorage());

        $savedSettings = $settingsService->getCategorySettings($category);

        $setSettings = false;

        foreach ($pathsKeys as $keys) {
            $current = &$savedSettings;
            $currentInit = &$initSettings;

            foreach ((array)$keys as $key) {
                if (!isset($current[$key])) {
                    $current[$key] = $currentInit[$key];
                    $setSettings = true;

                    continue 2;
                }

                $current = &$current[$key];
                $currentInit = &$initSettings[$key];
            }
        }

        if ($setSettings) {
            self::initSettings($category, $savedSettings, true);
        }
    }
}
