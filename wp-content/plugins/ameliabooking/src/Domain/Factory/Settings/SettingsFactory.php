<?php
/**
 * @copyright © TMS-Plugins. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Factory\Settings;

use AmeliaBooking\Domain\Entity\Settings\GeneralSettings;
use AmeliaBooking\Domain\Entity\Settings\Settings;
use AmeliaBooking\Domain\Entity\Settings\ZoomSettings;
use AmeliaBooking\Domain\ValueObjects\Json;

/**
 * Class SettingsFactory
 *
 * @package AmeliaBooking\Domain\Factory\Settings
 */
class SettingsFactory
{
    /**
     * @param Json  $entityJsonData
     * @param array $globalSettings
     *
     * @return Settings
     */
    public static function create($entityJsonData, $globalSettings)
    {
        $entitySettings = new Settings();
        $generalSettings = new GeneralSettings();
        $zoomSettings = new ZoomSettings();

        $data = $entityJsonData ? json_decode($entityJsonData->getValue(), true) : [];

        if (isset($data['general']['defaultAppointmentStatus'])) {
            $generalSettings->setDefaultAppointmentStatus($data['general']['defaultAppointmentStatus']);
        } else {
            $generalSettings->setDefaultAppointmentStatus($globalSettings['general']['defaultAppointmentStatus']);
        }

        if (isset($data['general']['minimumTimeRequirementPriorToBooking'])) {
            $generalSettings->setMinimumTimeRequirementPriorToBooking(
                $data['general']['minimumTimeRequirementPriorToBooking']
            );
        } else {
            $generalSettings->setMinimumTimeRequirementPriorToBooking(
                $globalSettings['general']['minimumTimeRequirementPriorToBooking']
            );
        }

        if (isset($data['general']['minimumTimeRequirementPriorToCanceling'])) {
            $generalSettings->setMinimumTimeRequirementPriorToCanceling(
                $data['general']['minimumTimeRequirementPriorToCanceling']
            );
        } else {
            $generalSettings->setMinimumTimeRequirementPriorToCanceling(
                $globalSettings['general']['minimumTimeRequirementPriorToCanceling']
            );
        }

        if (!empty($data['general']['numberOfDaysAvailableForBooking'])) {
            $generalSettings->setNumberOfDaysAvailableForBooking(
                $data['general']['numberOfDaysAvailableForBooking']
            );
        } else {
            $generalSettings->setNumberOfDaysAvailableForBooking(
                $globalSettings['general']['numberOfDaysAvailableForBooking']
            );
        }

        if (isset($data['zoom']['enabled'])) {
            $zoomSettings->setEnabled($data['zoom']['enabled']);
        } else {
            $zoomSettings->setEnabled($globalSettings['zoom']['enabled']);
        }

        $entitySettings->setGeneralSettings($generalSettings);
        $entitySettings->setZoomSettings($zoomSettings);

        return $entitySettings;
    }
}
