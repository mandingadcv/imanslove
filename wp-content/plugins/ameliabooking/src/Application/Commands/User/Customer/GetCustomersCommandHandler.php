<?php

namespace AmeliaBooking\Application\Commands\User\Customer;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\User\ProviderApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\User\CustomerRepository;
use Exception;
use Interop\Container\Exception\ContainerException;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class GetCustomersCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\User\Customer
 */
class GetCustomersCommandHandler extends CommandHandler
{
    /**
     * @param GetCustomersCommand $command
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws ContainerValueNotFoundException
     * @throws QueryExecutionException
     * @throws ContainerException
     * @throws Exception
     * @throws AccessDeniedException
     */
    public function handle(GetCustomersCommand $command)
    {
        if (!$this->getContainer()->getPermissionsService()->currentUserCanRead(Entities::CUSTOMERS)) {
            throw new AccessDeniedException('You are not allowed to read customers.');
        }

        $result = new CommandResult();

        /** @var CustomerRepository $customerRepository */
        $customerRepository = $this->getContainer()->get('domain.users.customers.repository');

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $params = $command->getField('params');
        $countParams = [];

        if (!$this->getContainer()->getPermissionsService()->currentUserCanReadOthers(Entities::CUSTOMERS)) {
            /** @var ProviderApplicationService $providerAS */
            $providerAS = $this->container->get('application.user.provider.service');

            /** @var AbstractUser $currentUser */
            $currentUser = $this->container->get('logged.in.user');

            /** @var Collection $providerCustomers */
            $providerCustomers = $providerAS->getAllowedCustomers($currentUser);

            $params['customers'] = array_column($providerCustomers->toArray(), 'id');
            $countParams['customers'] = $params['customers'];
        }

        $users = $customerRepository->getFiltered($params, $settingsService->getSetting('general', 'itemsPerPage'));

        foreach ($users as &$user) {
            $user['wpUserPhotoUrl'] = $this->container->get('user.avatar')->getAvatar($user['externalId']);

            $user = array_map(function ($v) {
                return (null === $v) ? '' : $v;
            }, $user);
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully retrieved users.');
        $result->setData([
            Entities::USER . 's' => $users,
            'filteredCount'      => (int)$customerRepository->getCount($params),
            'totalCount'         => (int)$customerRepository->getCount($countParams)
        ]);

        return $result;
    }
}
