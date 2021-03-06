<?php

namespace AmeliaBooking\Application\Services\Booking;

use AmeliaBooking\Application\Services\Gallery\GalleryApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Booking\Event\Event;
use AmeliaBooking\Domain\Entity\Booking\Event\EventPeriod;
use AmeliaBooking\Domain\Entity\Booking\Event\EventTag;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Factory\Booking\Appointment\AppointmentFactory;
use AmeliaBooking\Domain\Factory\Booking\Event\EventFactory;
use AmeliaBooking\Domain\Factory\Booking\Event\EventPeriodFactory;
use AmeliaBooking\Domain\Factory\Booking\Event\RecurringFactory;
use AmeliaBooking\Domain\Services\Booking\EventDomainService;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\ValueObjects\BooleanValueObject;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\WholeNumber;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Domain\ValueObjects\DateTime\DateTimeValue;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\CustomerBookingRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\CustomerBookingEventPeriodRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventPeriodsRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventProvidersRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventTagsRepository;
use AmeliaBooking\Infrastructure\Repository\Coupon\CouponEventRepository;
use AmeliaBooking\Infrastructure\Repository\CustomField\CustomFieldEventRepository;
use AmeliaBooking\Infrastructure\Repository\Payment\PaymentRepository;

/**
 * Class EventApplicationService
 *
 * @package AmeliaBooking\Application\Services\Booking
 */
class EventApplicationService
{
    private $container;

    /**
     * EventApplicationService constructor.
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
     * @param Event $event
     *
     * @return Collection
     *
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function add($event)
    {
        /** @var EventDomainService $eventDomainService */
        $eventDomainService = $this->container->get('domain.booking.event.service');

        $events = new Collection();

        if ($event->getRecurring()) {
            $event->getRecurring()->setOrder(new WholeNumber(1));
        }

        $this->addSingle($event);
        $events->addItem($event);
        $event->setParentId(new Id($event->getId()->getValue()));

        if ($event->getRecurring()) {
            /** @var Collection $recurringEventsPeriods */
            $recurringEventsPeriods = $eventDomainService->getRecurringEventsPeriods(
                $event->getRecurring(),
                $event->getPeriods()
            );

            /** @var Collection $recurringEventPeriods */
            foreach ($recurringEventsPeriods->getItems() as $key => $recurringEventPeriods) {
                $order = $event->getRecurring()->getOrder()->getValue() + 1;

                $event = EventFactory::create($event->toArray());

                $event->getRecurring()->setOrder(new WholeNumber($order));

                $event->setPeriods($recurringEventPeriods);
                $this->addSingle($event);
                $events->addItem($event);
            }
        }

        return $events;
    }

    /**
     * @param Event $oldEvent
     * @param Event $newEvent
     * @param bool  $updateFollowing
     *
     * @return array
     *
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws QueryExecutionException
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function update($oldEvent, $newEvent, $updateFollowing)
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = $this->container->get('domain.booking.event.repository');

        /** @var EventDomainService $eventDomainService */
        $eventDomainService = $this->container->get('domain.booking.event.service');

        /** @var Collection $rescheduledEvents */
        $rescheduledEvents = new Collection();

        /** @var Collection $clonedEvents */
        $clonedEvents = new Collection();

        /** @var Collection $addedEvents */
        $addedEvents = new Collection();

        /** @var Collection $deletedEvents */
        $deletedEvents = new Collection();

        $clonedEvents->addItem(EventFactory::create($oldEvent->toArray()), $oldEvent->getId()->getValue());

        $isNewRecurring = $this->isSeparateRecurringEvent($newEvent, $oldEvent);
        $isRescheduled = $newEvent->getPeriods()->toArray() !== $oldEvent->getPeriods()->toArray();

        if ($isNewRecurring) {
            $newEvent->getRecurring()->setOrder(new WholeNumber(1));
        }

        if ($isRescheduled) {
            $rescheduledEvents->addItem($newEvent, $newEvent->getId()->getValue());
        }

        $this->updateSingle($oldEvent, $newEvent, false);

        if (!$newEvent->getRecurring()) {
            $eventRepository->updateParentId($newEvent->getId()->getValue(), null);
        }

        // update following events parentId, if new event recurring value is removed and if it's origin event
        if (!$newEvent->getRecurring() && $oldEvent->getRecurring() && !$newEvent->getParentId()) {
            /** @var Collection $followingEvents */
            $followingEvents = $eventRepository->getFiltered([
                'parentId' => $newEvent->getId()->getValue()
            ]);

            $firstFollowingEventId = null;

            /** @var Event $followingEvent */
            foreach ($followingEvents->getItems() as $key => $followingEvent) {
                if (!$clonedEvents->keyExists($followingEvent->getId()->getValue())) {
                    $clonedEvents->addItem(EventFactory::create($followingEvent->toArray()), $followingEvent->getId()->getValue());
                }

                if ($followingEvent->getId()->getValue() > $newEvent->getId()->getValue()) {
                    $eventRepository->updateParentId($followingEvent->getId()->getValue(), $firstFollowingEventId);

                    if ($firstFollowingEventId === null) {
                        $firstFollowingEventId = $followingEvent->getId()->getValue();
                    }
                }
            }
        }

        if ($updateFollowing && $newEvent->getRecurring()) {
            /** @var Collection $followingEvents */
            $followingEvents = $eventRepository->getFiltered([
                'parentId' => $newEvent->getParentId() ?
                    $newEvent->getParentId()->getValue() : $newEvent->getId()->getValue()
            ]);

            /** @var Event $firstEvent **/
            $firstEvent = $followingEvents->getItem($followingEvents->keys()[0]);

            /** @var Collection $clonedOriginEventPeriods **/
            $clonedOriginEventPeriods = $eventDomainService->getClonedEventPeriods(
                $isNewRecurring ? $newEvent->getPeriods() : $firstEvent->getPeriods(),
                false
            );

            $followingRecurringOrder = $newEvent->getRecurring()->getOrder()->getValue();

            /** @var Event $followingEvent */
            foreach ($followingEvents->getItems() as $key => $followingEvent) {
                if (!$clonedEvents->keyExists($followingEvent->getId()->getValue())) {
                    $clonedEvents->addItem(EventFactory::create($followingEvent->toArray()), $followingEvent->getId()->getValue());
                }

                if ($followingEvent->getId()->getValue() < $newEvent->getId()->getValue()) {
                    $followingEvent->getRecurring()->setUntil(
                        $isNewRecurring ?
                            $newEvent->getPeriods()->getItem(0)->getPeriodStart() :
                            $newEvent->getRecurring()->getUntil()
                    );

                    $this->updateSingle($followingEvent, $followingEvent, true);
                }

                if ($isNewRecurring && $followingEvent->getId()->getValue() === $newEvent->getId()->getValue()) {
                    $eventRepository->updateParentId($newEvent->getId()->getValue(), null);
                }

                if ($followingEvent->getId()->getValue() > $newEvent->getId()->getValue()) {
                    $followingEvent->setRecurring(RecurringFactory::create(
                        [
                            'cycle' => $newEvent->getRecurring()->getCycle()->getValue(),
                            'until' => $newEvent->getRecurring()->getUntil()->getValue()->format('Y-m-d H:i:s'),
                            'order' => $isNewRecurring ?
                                ++$followingRecurringOrder : $followingEvent->getRecurring()->getOrder()->getValue()
                        ]
                    ));

                    /** @var Collection $clonedFollowingEventPeriods */
                    $clonedFollowingEventPeriods = $eventDomainService->getClonedEventPeriods(
                        $followingEvent->getPeriods(),
                        true
                    );

                    $eventDomainService->buildFollowingEvent($followingEvent, $newEvent, $clonedOriginEventPeriods);

                    if ($isRescheduled && $followingEvent->getStatus()->getValue() === BookingStatus::APPROVED) {
                        $rescheduledEvents->addItem($followingEvent, $followingEvent->getId()->getValue());
                    }

                    /** @var EventPeriod $firstPeriod */
                    $firstPeriod = $followingEvent->getPeriods()->getItem(0);

                    if ($firstPeriod->getPeriodStart()->getValue() <= $newEvent->getRecurring()->getUntil()->getValue()) {
                        if ($isNewRecurring) {
                            $followingEvent->setParentId($newEvent->getId());
                        }

                        $followingEventClone = EventFactory::create($followingEvent->toArray());

                        $followingEventClone->setPeriods($clonedFollowingEventPeriods);

                        $this->updateSingle($followingEventClone, $followingEvent, false);
                    } else {
                        $this->deleteEvent($followingEvent);

                        $deletedEvents->addItem($followingEvent, $followingEvent->getId()->getValue());
                    }
                }
            }

            /** @var Event $lastEvent **/
            $lastEvent = $followingEvents->getItem($followingEvents->keys()[sizeof($followingEvents->keys()) - 1]);

            $lastRecurringOrder = $lastEvent->getRecurring()->getOrder()->getValue();

            while (
                $lastEvent->getPeriods()->getItem(0)->getPeriodStart()->getValue() <=
                $newEvent->getRecurring()->getUntil()->getValue()
            ) {
                /** @var Event $lastEvent **/
                $lastEvent = EventFactory::create([
                    'name'  => $newEvent->getName()->getValue(),
                    'price' => $newEvent->getPrice()->getValue(),
                ]);

                $lastEvent->setRecurring(RecurringFactory::create(
                    [
                        'cycle' => $newEvent->getRecurring()->getCycle()->getValue(),
                        'until' => $newEvent->getRecurring()->getUntil()->getValue()->format('Y-m-d H:i:s'),
                        'order' => ++$lastRecurringOrder
                    ]
                ));

                $lastEvent->setPeriods($eventDomainService->getClonedEventPeriods($clonedOriginEventPeriods, false));

                $eventDomainService->buildFollowingEvent(
                    $lastEvent,
                    $newEvent,
                    $eventDomainService->getClonedEventPeriods($clonedOriginEventPeriods, false)
                );

                $lastEvent->setParentId(
                    !$isNewRecurring && $newEvent->getParentId() ?
                        $newEvent->getParentId() : $newEvent->getId()
                );

                if ($lastEvent->getPeriods()->getItem(0)->getPeriodStart()->getValue() <=
                    $newEvent->getRecurring()->getUntil()->getValue()
                ) {
                    /** @var EventPeriod $eventPeriod **/
                    foreach ($lastEvent->getPeriods()->getItems() as $key => $eventPeriod) {
                        /** @var EventPeriod $newEventPeriod **/
                        $newEventPeriod = EventPeriodFactory::create(array_merge(
                            $eventPeriod->toArray(),
                            ['zoomMeeting' => null]
                        ));

                        $lastEvent->getPeriods()->placeItem($newEventPeriod, $key, true);
                    }

                    $this->addSingle($lastEvent);

                    $addedEvents->addItem($lastEvent, $lastEvent->getId()->getValue());
                }
            }
        }

        if ($newEvent->getZoomUserId() && !$oldEvent->getZoomUserId()) {
            /** @var Event $event **/
            foreach ($clonedEvents->getItems() as $event) {
                $event->setZoomUserId($newEvent->getZoomUserId());
            }
        }

        if ($newEvent->getDescription() &&
            (
                ($newEvent->getDescription() ? $newEvent->getDescription()->getValue() : null) !==
                ($oldEvent->getDescription() ? $oldEvent->getDescription()->getValue() : null) ||
                $newEvent->getName()->getValue() !== $oldEvent->getName()->getValue()
            )
        ) {
            /** @var Event $event **/
            foreach ($clonedEvents->getItems() as $event) {
                $event->setDescription($newEvent->getDescription());
            }
        }

        return [
            'rescheduled' => $rescheduledEvents->toArray(),
            'added'       => $addedEvents->toArray(),
            'deleted'     => $deletedEvents->toArray(),
            'cloned'      => $clonedEvents->toArray()
        ];
    }

    /**
     * @param Event  $event
     * @param String $status
     * @param bool   $updateFollowing
     *
     * @return Collection
     *
     * @throws \Slim\Exception\ContainerException
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws \InvalidArgumentException
     * @throws \Interop\Container\Exception\ContainerException
     * @throws QueryExecutionException
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     */
    public function updateStatus($event, $status, $updateFollowing)
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = $this->container->get('domain.booking.event.repository');

        /** @var CustomerBookingRepository $bookingRepository */
        $bookingRepository = $this->container->get('domain.booking.customerBooking.repository');

        /** @var Collection $updatedEvents */
        $updatedEvents = new Collection();

        if ($event->getStatus()->getValue() !== $status) {
            $eventRepository->updateStatusById($event->getId()->getValue(), $status);

            $event->setStatus(new BookingStatus($status));

            $updatedEvents->addItem($event, $event->getId()->getValue());

            /** @var CustomerBooking $booking */
            foreach ($event->getBookings()->getItems() as $booking) {
                if ($status === BookingStatus::REJECTED &&
                    $booking->getStatus()->getValue() === BookingStatus::APPROVED
                ) {
                    $bookingRepository->updateStatusById($booking->getId()->getValue(), BookingStatus::REJECTED);
                    $booking->setChangedStatus(new BooleanValueObject(true));
                }
            }
        }

        if ($updateFollowing) {
            /** @var Collection $followingEvents */
            $followingEvents = $eventRepository->getFiltered([
                'parentId' => $event->getParentId() ?
                    $event->getParentId()->getValue() : $event->getId()->getValue()
            ]);

            /** @var Event $followingEvent */
            foreach ($followingEvents->getItems() as $key => $followingEvent) {
                if ($followingEvent->getId()->getValue() > $event->getId()->getValue()) {
                    $followingEventStatus = $followingEvent->getStatus()->getValue();

                    if (($status === BookingStatus::APPROVED && $followingEventStatus === BookingStatus::REJECTED) ||
                        ($status === BookingStatus::REJECTED && $followingEventStatus === BookingStatus::APPROVED)
                    ) {
                        /** @var CustomerBooking $booking */
                        foreach ($followingEvent->getBookings()->getItems() as $booking) {
                            if ($status === BookingStatus::REJECTED &&
                                $booking->getStatus()->getValue() === BookingStatus::APPROVED
                            ) {
                                $bookingRepository->updateStatusById(
                                    $booking->getId()->getValue(),
                                    BookingStatus::REJECTED
                                );

                                $booking->setChangedStatus(new BooleanValueObject(true));
                            }
                        }

                        $eventRepository->updateStatusById($followingEvent->getId()->getValue(), $status);

                        $followingEvent->setStatus(new BookingStatus($status));

                        $updatedEvents->addItem($followingEvent, $followingEvent->getId()->getValue());
                    }
                }
            }
        }

        return $updatedEvents;
    }

    /**
     * @param Event  $event
     * @param bool   $deleteFollowing
     *
     * @return bool
     *
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws QueryExecutionException
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function delete($event, $deleteFollowing)
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = $this->container->get('domain.booking.event.repository');

        /** @var Collection $recurringEvents */
        $recurringEvents = $eventRepository->getFiltered([
            'parentId' => $event->getParentId() ?
                $event->getParentId()->getValue() : $event->getId()->getValue()
        ]);

        /** @var Event $newOriginRecurringEvent **/
        $newOriginRecurringEvent = null;

        $hasRecurringApprovedEvents = false;

        /** @var Event $recurringEvent */
        foreach ($recurringEvents->getItems() as $key => $recurringEvent) {
            // delete event
            if ($recurringEvent->getId()->getValue() === $event->getId()->getValue()) {
                $this->deleteEvent($recurringEvent);
            }

            if ($recurringEvent->getId()->getValue() > $event->getId()->getValue()) {
                $recurringEventStatus = $recurringEvent->getStatus()->getValue();

                // delete following recurring events if they are canceled
                if ($deleteFollowing && $recurringEventStatus === BookingStatus::REJECTED) {
                    $this->deleteEvent($recurringEvent);
                }

                if ($recurringEventStatus === BookingStatus::APPROVED) {
                    $hasRecurringApprovedEvents = true;

                    // update following recurring events if they are approved and if origin event is deleted
                    if ($event->getParentId() === null) {
                        if ($newOriginRecurringEvent === null) {
                            $newOriginRecurringEvent = $recurringEvent;
                        }

                        $eventRepository->updateParentId(
                            $recurringEvent->getId()->getValue(),
                            $newOriginRecurringEvent->getId()->getValue() === $recurringEvent->getId()->getValue() ?
                                null :
                                $newOriginRecurringEvent->getId()->getValue()
                        );
                    }
                }
            }
        }

        // update recurring time for previous recurring events if there are no following recurring events
        if (!$hasRecurringApprovedEvents) {
            /** @var Event $recurringEvent */
            foreach ($recurringEvents->getItems() as $key => $recurringEvent) {
                if ($recurringEvent->getId()->getValue() < $event->getId()->getValue()) {
                    $recurringEvent->getRecurring()->setUntil(
                        $event->getPeriods()->getItem(0)->getPeriodStart()
                    );

                    $this->updateSingle($recurringEvent, $recurringEvent, true);
                }
            }
        }

        return true;
    }

    /**
     * @param Event $event
     *
     * @return void
     *
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     */
    private function addSingle($event)
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = $this->container->get('domain.booking.event.repository');

        /** @var EventPeriodsRepository $eventPeriodsRepository */
        $eventPeriodsRepository = $this->container->get('domain.booking.event.period.repository');

        /** @var EventTagsRepository $eventTagsRepository */
        $eventTagsRepository = $this->container->get('domain.booking.event.tag.repository');

        /** @var EventProvidersRepository $eventProvidersRepository */
        $eventProvidersRepository = $this->container->get('domain.booking.event.provider.repository');

        /** @var GalleryApplicationService $galleryService */
        $galleryService = $this->container->get('application.gallery.service');

        $event->setStatus(new BookingStatus(BookingStatus::APPROVED));
        $event->setNotifyParticipants(1);
        $event->setCreated(new DateTimeValue(DateTimeService::getNowDateTimeObject()));

        $eventId = $eventRepository->add($event);

        $event->setId(new Id($eventId));

        /** @var EventPeriod $eventPeriod */
        foreach ($event->getPeriods()->getItems() as $eventPeriod) {
            $eventPeriod->setEventId(new Id($eventId));

            $eventPeriodId = $eventPeriodsRepository->add($eventPeriod);

            $eventPeriod->setId(new Id($eventPeriodId));
        }

        /** @var EventTag $eventTag */
        foreach ($event->getTags()->getItems() as $eventTag) {
            $eventTag->setEventId(new Id($eventId));

            $eventTagId = $eventTagsRepository->add($eventTag);

            $eventTag->setId(new Id($eventTagId));
        }

        /** @var Provider $provider */
        foreach ($event->getProviders()->getItems() as $provider) {
            $eventProvidersRepository->add($event, $provider);
        }

        $galleryService->manageGalleryForEntityAdd($event->getGallery(), $event->getId()->getValue());
    }

    /**
     * @param Event $oldEvent
     * @param Event $newEvent
     * @param bool  $isPreviousEvent
     *
     * @return void
     *
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     */
    private function updateSingle($oldEvent, $newEvent, $isPreviousEvent)
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = $this->container->get('domain.booking.event.repository');

        /** @var EventPeriodsRepository $eventPeriodsRepository */
        $eventPeriodsRepository = $this->container->get('domain.booking.event.period.repository');

        /** @var EventProvidersRepository $eventProvidersRepository */
        $eventProvidersRepository = $this->container->get('domain.booking.event.provider.repository');

        /** @var GalleryApplicationService $galleryService */
        $galleryService = $this->container->get('application.gallery.service');

        $eventId = $newEvent->getId()->getValue();

        if (!$isPreviousEvent) {
            /** @var EventTagsRepository $eventTagsRepository */
            $eventTagsRepository = $this->container->get('domain.booking.event.tag.repository');

            $eventTagsRepository->deleteByEventId($oldEvent->getId()->getValue());

            /** @var EventTag $eventTag */
            forEach ($newEvent->getTags()->getItems() as $eventTag) {
                $eventTag->setEventId($newEvent->getId());

                $eventTagId = $eventTagsRepository->add($eventTag);

                $eventTag->setId(new Id($eventTagId));
            }

            $eventProvidersRepository->deleteByEventId($eventId);

            /** @var Provider $provider */
            foreach ($newEvent->getProviders()->getItems() as $provider) {
                $eventProvidersRepository->add($newEvent, $provider);
            }
        }

        $newEvent->setStatus($oldEvent->getStatus());
        $newEvent->setNotifyParticipants(1);

        $oldPeriodsIds = [];
        $newPeriodsIds = [];

        /** @var EventPeriod $eventPeriod */
        forEach ($oldEvent->getPeriods()->getItems() as $eventPeriod) {
            $oldPeriodsIds[] = $eventPeriod->getId()->getValue();
        }

        /** @var EventPeriod $eventPeriod */
        forEach ($newEvent->getPeriods()->getItems() as $eventPeriod) {
            if ($eventPeriod->getId()) {
                $newPeriodsIds[] = $eventPeriod->getId()->getValue();

                $eventPeriodsRepository->update($eventPeriod->getId()->getValue(), $eventPeriod);
            } else {
                $eventPeriodId = $eventPeriodsRepository->add($eventPeriod);

                $eventPeriod->setId(new Id($eventPeriodId));
            }
        }

        foreach (array_diff($oldPeriodsIds, $newPeriodsIds) as $eventPeriodId) {
            $eventPeriodsRepository->delete($eventPeriodId);
        }

        $galleryService->manageGalleryForEntityUpdate($newEvent->getGallery(), $eventId, Entities::EVENT);

        $eventRepository->update($eventId, $newEvent);
    }

    /**
     * @param Event $newEvent
     * @param Event $oldEvent
     *
     * @return bool
     *
     */
    private function isSeparateRecurringEvent($newEvent, $oldEvent)
    {
        return $newEvent->getRecurring() && (
                $newEvent->getPeriods()->toArray() !== $oldEvent->getPeriods()->toArray() ||
                $newEvent->getRecurring()->getCycle()->getValue() !== ($oldEvent->getRecurring() ? $oldEvent->getRecurring()->getCycle()->getValue() : true)
            );
    }

    /**
     * @param Collection $providers
     * @param array      $dates
     *
     * @return void
     *
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws \Interop\Container\Exception\ContainerException
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     * @throws QueryExecutionException
     * @throws \Exception
     */
    public function removeSlotsFromEvents($providers, $dates)
    {
        $providersIds = [];

        /** @var Provider $provider */
        foreach ($providers->getItems() as $provider) {
            $providersIds[] = $provider->getId()->getValue();
        }

        /** @var EventRepository $eventRepository */
        $eventRepository = $this->container->get('domain.booking.event.repository');

        /** @var Collection $events */
        $events = $eventRepository->getProvidersEvents([
            'providers' => $providersIds,
            'dates'     => $dates,
            'status'    => BookingStatus::APPROVED,
        ]);

        /** @var Event $event */
        foreach ($events->getItems() as $event) {
            /** @var Provider $provider */
            foreach ($providers->getItems() as $provider) {
                if ($event->getProviders()->keyExists($provider->getId()->getValue())) {
                    /** @var EventPeriod $period */
                    foreach ($event->getPeriods()->getItems() as $period) {
                        $range = new \DatePeriod(
                            $period->getPeriodStart()->getValue(),
                            new \DateInterval('P1D'),
                            $period->getPeriodEnd()->getValue()
                        );

                        $eventStartTimeString = $period->getPeriodStart()->getValue()->format('H:i:s');
                        $eventEndTimeString = $period->getPeriodEnd()->getValue()->format('H:i:s');

                        /** @var \DateTime $date */
                        foreach ($range as $date) {
                            $appointment = AppointmentFactory::create([
                                'bookingStart'       => $date->format('Y-m-d') . ' ' . $eventStartTimeString,
                                'bookingEnd'         => $date->format('Y-m-d') . ' ' . $eventEndTimeString,
                                'notifyParticipants' => false,
                                'serviceId'          => 0,
                                'providerId'         => $provider->getId()->getValue(),
                            ]);

                            $provider->getAppointmentList()->addItem($appointment);
                        }
                    }
                }
            }
        }
    }

    /**
     *
     * @param Event $event
     *
     * @return boolean
     *
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function deleteEvent($event)
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = $this->container->get('domain.booking.event.repository');

        /** @var EventPeriodsRepository $eventPeriodsRepository */
        $eventPeriodsRepository = $this->container->get('domain.booking.event.period.repository');

        /** @var EventProvidersRepository $eventProvidersRepository */
        $eventProvidersRepository = $this->container->get('domain.booking.event.provider.repository');

        /** @var CouponEventRepository $couponEventRepository */
        $couponEventRepository = $this->container->get('domain.coupon.event.repository');

        /** @var CustomFieldEventRepository $customFieldEventRepository */
        $customFieldEventRepository = $this->container->get('domain.customFieldEvent.repository');

        /** @var EventTagsRepository $eventTagsRepository */
        $eventTagsRepository = $this->container->get('domain.booking.event.tag.repository');

        /** @var GalleryApplicationService $galleryService */
        $galleryService = $this->container->get('application.gallery.service');

        /** @var CustomerBooking $booking */
        foreach ($event->getBookings()->getItems() as $booking) {
            if (!$this->deleteEventBooking($booking)) {
                return false;
            }
        }

        /** @var EventPeriod $eventPeriod */
        forEach ($event->getPeriods()->getItems() as $eventPeriod) {
            if (!$eventPeriodsRepository->delete($eventPeriod->getId()->getValue())) {
                return false;
            }
        }

        return
            $eventProvidersRepository->deleteByEntityId($event->getId()->getValue(), 'eventId') &&
            $couponEventRepository->deleteByEntityId($event->getId()->getValue(), 'eventId') &&
            $customFieldEventRepository->deleteByEntityId($event->getId()->getValue(), 'eventId') &&
            $eventTagsRepository->deleteByEntityId($event->getId()->getValue(), 'eventId') &&
            $galleryService->manageGalleryForEntityDelete($event->getGallery()) &&
            $eventRepository->delete($event->getId()->getValue());
    }

    /**
     *
     * @param CustomerBooking $booking
     *
     * @return boolean
     *
     * @throws \Slim\Exception\ContainerValueNotFoundException
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function deleteEventBooking($booking)
    {
        /** @var CustomerBookingRepository $bookingRepository */
        $bookingRepository = $this->container->get('domain.booking.customerBooking.repository');

        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->container->get('domain.payment.repository');

        /** @var CustomerBookingEventPeriodRepository $bookingEventPeriodRepository */
        $bookingEventPeriodRepository = $this->container->get('domain.booking.customerBookingEventPeriod.repository');

        return
            $bookingEventPeriodRepository->deleteByEntityId($booking->getId()->getValue(), 'customerBookingId') &&
            $paymentRepository->deleteByEntityId($booking->getId()->getValue(), 'customerBookingId') &&
            $bookingRepository->delete($booking->getId()->getValue());
    }
}
