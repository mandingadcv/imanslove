<?php
/**
 * @copyright © TMS-Plugins. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Entity\Settings;

/**
 * Class GeneralSettings
 *
 * @package AmeliaBooking\Domain\Entity\Settings
 */
class GeneralSettings
{
    /** @var int */
    private $minimumTimeRequirementPriorToBooking;

    /** @var int */
    private $minimumTimeRequirementPriorToCanceling;

    /** @var string */
    private $defaultAppointmentStatus;

    /** @var int */
    private $numberOfDaysAvailableForBooking;

    /**
     * @return int
     */
    public function getMinimumTimeRequirementPriorToBooking()
    {
        return $this->minimumTimeRequirementPriorToBooking;
    }

    /**
     * @param int $minimumTimeRequirementPriorToBooking
     */
    public function setMinimumTimeRequirementPriorToBooking($minimumTimeRequirementPriorToBooking)
    {
        $this->minimumTimeRequirementPriorToBooking = $minimumTimeRequirementPriorToBooking;
    }

    /**
     * @return int
     */
    public function getMinimumTimeRequirementPriorToCanceling()
    {
        return $this->minimumTimeRequirementPriorToCanceling;
    }

    /**
     * @param int $minimumTimeRequirementPriorToCanceling
     */
    public function setMinimumTimeRequirementPriorToCanceling($minimumTimeRequirementPriorToCanceling)
    {
        $this->minimumTimeRequirementPriorToCanceling = $minimumTimeRequirementPriorToCanceling;
    }

    /**
     * @return string
     */
    public function getDefaultAppointmentStatus()
    {
        return $this->defaultAppointmentStatus;
    }

    /**
     * @param string $defaultAppointmentStatus
     */
    public function setDefaultAppointmentStatus($defaultAppointmentStatus)
    {
        $this->defaultAppointmentStatus = $defaultAppointmentStatus;
    }

    /**
     * @return int
     */
    public function getNumberOfDaysAvailableForBooking()
    {
        return $this->numberOfDaysAvailableForBooking;
    }

    /**
     * @param int $numberOfDaysAvailableForBooking
     */
    public function setNumberOfDaysAvailableForBooking($numberOfDaysAvailableForBooking)
    {
        $this->numberOfDaysAvailableForBooking = $numberOfDaysAvailableForBooking;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'minimumTimeRequirementPriorToBooking'   => $this->getMinimumTimeRequirementPriorToBooking(),
            'minimumTimeRequirementPriorToCanceling' => $this->getMinimumTimeRequirementPriorToCanceling(),
            'defaultAppointmentStatus'               => $this->getDefaultAppointmentStatus(),
            'numberOfDaysAvailableForBooking'        => $this->getNumberOfDaysAvailableForBooking(),
        ];
    }
}
