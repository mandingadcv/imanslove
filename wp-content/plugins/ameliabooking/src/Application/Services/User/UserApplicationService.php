<?php

namespace AmeliaBooking\Application\Services\User;

use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Helper\HelperService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\AuthorizationException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Entity\User\Customer;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\Json;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\LoginType;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;
use AmeliaBooking\Infrastructure\Repository\User\UserRepository;
use AmeliaBooking\Infrastructure\WP\UserService\CreateWPUser;
use AmeliaBooking\Infrastructure\WP\UserService\UserService;
use Exception;
use Firebase\JWT\JWT;
use Interop\Container\Exception\ContainerException;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class UserApplicationService
 *
 * @package AmeliaBooking\Application\Services\User
 */
class UserApplicationService
{
    private $container;

    /**
     * ProviderApplicationService constructor.
     *
     * @param Container $container
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     *
     * @param int $userId
     *
     * @return array
     *
     * @throws ContainerValueNotFoundException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function getAppointmentsCountForUser($userId)
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->container->get('domain.users.repository');

        /** @var AbstractUser $user */
        $user = $userRepository->getById($userId);

        /** @var AppointmentRepository $appointmentRepo */
        $appointmentRepo = $this->container->get('domain.booking.appointment.repository');

        $appointments = new Collection();

        switch ($user->getType()) {
            case (AbstractUser::USER_ROLE_PROVIDER):
                $appointments = $appointmentRepo->getFiltered(['providerId' => $userId]);

                break;
            case (AbstractUser::USER_ROLE_CUSTOMER):
                $appointments = $appointmentRepo->getFiltered(['customerId' => $userId]);

                break;
        }

        $now = DateTimeService::getNowDateTimeObject();

        $futureAppointments = 0;
        $pastAppointments = 0;

        foreach ((array)$appointments->keys() as $appointmentKey) {
            if ($appointments->getItem($appointmentKey)->getBookingStart()->getValue() >= $now) {
                $futureAppointments++;
            } else {
                $pastAppointments++;
            }
        }

        return [
            'futureAppointments' => $futureAppointments,
            'pastAppointments'   => $pastAppointments
        ];
    }

    /**
     * @param int          $userId
     * @param AbstractUser $user
     *
     * @throws ContainerValueNotFoundException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    public function setWpUserIdForNewUser($userId, $user)
    {
        /** @var CreateWPUser $createWPUserService */
        $createWPUserService = $this->container->get('user.create.wp.user');

        $externalId = $createWPUserService->create(
            $user->getEmail()->getValue(),
            $user->getFirstName() ? $user->getFirstName()->getValue() : '',
            $user->getLastName() ? $user->getLastName()->getValue() : '',
            'wpamelia-' . $user->getType()
        );

        /** @var UserRepository $userRepository */
        $userRepository = $this->container->get('domain.users.repository');

        if ($externalId && !$userRepository->findByExternalId($externalId)) {
            $user->setExternalId(new Id($externalId));
            $userRepository->update($userId, $user);
        }
    }

    /**
     * @param int          $userId
     * @param AbstractUser $user
     *
     * @throws ContainerValueNotFoundException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    public function setWpUserIdForExistingUser($userId, $user)
    {
        /** @var CreateWPUser $createWPUserService */
        $createWPUserService = $this->container->get('user.create.wp.user');

        $externalId = $user->getExternalId()->getValue();

        /** @var AbstractUser $wpUser */
        $wpUser = $this->container->get('logged.in.user');

        if (!$wpUser && $user->getExternalId()) {
            /** @var UserService $userService */
            $userService = $this->container->get('users.service');

            $wpUser = $userService->getWpUserById($user->getExternalId()->getValue());
        }

        if ($wpUser && $wpUser->getType() !== AbstractUser::USER_ROLE_ADMIN) {
            $createWPUserService->update(
                $externalId,
                'wpamelia-' . $user->getType()
            );
        }

        /** @var UserRepository $userRepository */
        $userRepository = $this->container->get('domain.users.repository');

        if ($externalId && !$userRepository->findByExternalId($externalId)) {
            $user->setExternalId(new Id($externalId));
            $userRepository->update($userId, $user);
        }
    }

    /**
     * @param AbstractUser $user
     *
     * @return boolean
     *
     */
    public function isCustomer($user)
    {
        return $user === null || $user->getType() === Entities::CUSTOMER;
    }

    /**
     * @param AbstractUser $user
     *
     * @return boolean
     *
     */
    public function isProvider($user)
    {
        return $user === null || $user->getType() === Entities::PROVIDER;
    }

    /**
     * @param Provider|Customer $user
     * @param boolean           $sendToken
     * @param boolean           $checkIfSavedPassword
     * @param int               $loginType
     * @param string            $cabinetType
     *
     * @return CommandResult
     *
     * @throws ContainerException
     * @throws Exception
     */
    public function getAuthenticatedUserResponse($user, $sendToken, $checkIfSavedPassword, $loginType, $cabinetType)
    {
        $result = new CommandResult();

        if ($user->getType() !== $cabinetType && $user->getType() !== AbstractUser::USER_ROLE_ADMIN) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Could not retrieve user');
            $result->setData(['invalid_credentials' => true]);

            return $result;
        }

        /** @var HelperService $helperService */
        $helperService = $this->container->get('application.helper.service');

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var array $cabinetSettings */
        $cabinetSettings = $settingsService->getSetting('roles', $cabinetType . 'Cabinet');

        /** @var ProviderApplicationService $providerService */
        $providerService = $this->container->get('application.user.provider.service');

        // If cabinet is for provider, return provider with services and schedule
        if ($cabinetType === AbstractUser::USER_ROLE_PROVIDER) {
            $password = $user->getPassword();
            $user = $providerService->getProviderWithServicesAndSchedule($user->getId()->getValue());
            $user->setPassword($password);
        }

        /** @var array $userArray */
        $userArray = $user->toArray();

        // Set activity if it is employee cabinet
        if ($cabinetType === AbstractUser::USER_ROLE_PROVIDER) {
            $companyDaysOff = $settingsService->getCategorySettings('daysOff');
            $companyDayOff = $providerService->checkIfTodayIsCompanyDayOff($companyDaysOff);

            $userArray = $providerService->manageProvidersActivity(
                [$userArray],
                $companyDayOff
            )[0];
        }

        $responseData = [
            Entities::USER => $userArray,
            'is_wp_user'   => $loginType === LoginType::WP_USER
        ];

        if (($loginType === LoginType::AMELIA_URL_TOKEN || $loginType === LoginType::AMELIA_CREDENTIALS) &&
            $checkIfSavedPassword &&
            $cabinetSettings['loginEnabled'] &&
            ($user->getPassword() === null || $user->getPassword()->getValue() === null)
        ) {
            $responseData['set_password'] = true;
        }

        if ($sendToken) {
            $responseData['token'] = $helperService->getGeneratedJWT(
                $user->getEmail()->getValue(),
                $cabinetSettings['headerJwtSecret'],
                DateTimeService::getNowDateTimeObject()->getTimestamp() + $cabinetSettings['tokenValidTime'],
                $loginType
            );
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully');
        $result->setData($responseData);

        return $result;
    }

    /**
     * @param $token
     * @param $isUrlToken
     * @param $cabinetType
     *
     * @return AbstractUser|null
     *
     * @throws AccessDeniedException
     * @throws ContainerException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function getAuthenticatedUser($token, $isUrlToken, $cabinetType)
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var array $cabinetSettings */
        $cabinetSettings = $settingsService->getSetting('roles', $cabinetType . 'Cabinet');

        if (!$cabinetSettings['enabled']) {
            throw new AccessDeniedException('You are not allowed to access this page.');
        }

        try {
            $jwtObject = JWT::decode(
                $token,
                $cabinetSettings[$isUrlToken ? 'urlJwtSecret' : 'headerJwtSecret'],
                array('HS256')
            );
        } catch (Exception $e) {
            return null;
        }

        /** @var UserRepository $userRepository */
        $userRepository = $this->container->get('domain.users.repository');

        /** @var Customer $user */
        $user = $userRepository->getByEmail($jwtObject->email, true, true);

        if (!($user instanceof AbstractUser)) {
            return null;
        }

        $user->setLoginType($jwtObject->wp);

        if ($isUrlToken) {
            $usedTokens = $user->getUsedTokens() && $user->getUsedTokens()->getValue() ?
                json_decode($user->getUsedTokens()->getValue(), true) : [];

            if (in_array($token, $usedTokens, true)) {
                return null;
            }

            $currentTimeStamp = DateTimeService::getNowDateTimeObject()->getTimestamp() +
                $cabinetSettings['tokenValidTime'];

            foreach ($usedTokens as $tokenKey => $usedToken) {
                if ($tokenKey < $currentTimeStamp) {
                    unset($usedTokens[$tokenKey]);
                }
            }

            $usedTokens[$jwtObject->exp] = $token;

            $newUsedTokens = new Json(json_encode($usedTokens));

            $userRepository->updateFieldById($user->getId()->getValue(), $newUsedTokens->getValue(), 'usedTokens');
        }

        return $user;
    }

    /**
     * @param string $token
     * @param string $cabinetType
     *
     * @return AbstractUser
     *
     * @throws AccessDeniedException
     * @throws AuthorizationException
     * @throws ContainerException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function authorization($token, $cabinetType)
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var array $cabinetSettings */
        $cabinetSettings = $settingsService->getSetting('roles', $cabinetType . 'Cabinet');

        /** @var AbstractUser $user */
        $user = $this->container->get('logged.in.user');

        $isAmeliaWPUser = $user && $user->getId() !== null;

        // check if token exist and user is not logged in as Word Press User and token is valid
        if ($token && !$isAmeliaWPUser && ($user = $this->getAuthenticatedUser($token, false, $cabinetType)) === null) {
            throw new AuthorizationException('Authorization Exception.');
        }

        if ($user && !$isAmeliaWPUser && $user->getLoginType() === LoginType::WP_USER) {
            throw new AuthorizationException('Authorization Exception.');
        }

        // if user is not logged in as Word Press User or token not exist/valid
        if (!$this->isAmeliaUser($user)) {
            throw new AuthorizationException('Authorization Exception.');
        }

        $userType = $user->getType();

        // check if user is not logged in as Word Press User and password is required and password is not created
        if (!$isAmeliaWPUser &&
            $userType !== AbstractUser::USER_ROLE_ADMIN &&
            $userType !== AbstractUser::USER_ROLE_MANAGER &&
            $cabinetSettings['loginEnabled'] &&
            $this->isAmeliaUser($user) &&
            ($user->getLoginType() === LoginType::AMELIA_URL_TOKEN ||
                $user->getLoginType() === LoginType::AMELIA_CREDENTIALS
            ) && (!$user->getPassword() || !$user->getPassword()->getValue())
        ) {
            throw new AuthorizationException('Authorization Exception.');
        }

        return $user;
    }

    /**
     * @param AbstractUser $user
     *
     * @return boolean
     *
     */
    public function isAmeliaUser($user)
    {
        return $user &&
            (
                $user->getId() !== null ||
                $user->getType() === AbstractUser::USER_ROLE_ADMIN ||
                $user->getType() === AbstractUser::USER_ROLE_MANAGER
            );
    }
}
