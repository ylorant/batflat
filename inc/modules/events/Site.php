<?php

/**
 * This file is part of Batflat ~ the lightweight, fast and easy CMS
 *
 * @author       Paweł Klockiewicz <klockiewicz@sruu.pl>
 * @author       Wojciech Król <krol@sruu.pl>
 * @copyright    2017 Paweł Klockiewicz, Wojciech Król <Sruu.pl>
 * @license      https://batflat.org/license
 * @link         https://batflat.org
 */

namespace Inc\Modules\Events;

use DateTimeImmutable;
use DateTimeZone;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\ValueObject\Attachment;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\GeographicPosition;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Exception;
use Inc\Core\SiteModule;
use IntlDateFormatter;
use stdClass;

/**
 * events site class
 */
class Site extends SiteModule
{
    public const DEFAULT_SLUG = 'planning';
    public const DEFAULT_EVENT_SLUG = 'event';
    public const DEFAULT_HORARO_URL = 'https://horaro.org';
    public const DEFAULT_ICAL_NAME = 'cal';

    /** @var string */
    protected string $baseSlug = self::DEFAULT_SLUG;

    /** @var string */
    protected string $eventSlug = self::DEFAULT_EVENT_SLUG;

    /** @var string */
    protected string $icalName = self::DEFAULT_ICAL_NAME;

    /** @var string|null */
    protected ?string $moduleDirectory = null;
    
    /** @var HoraroAPI Horaro API Client instance */
    protected HoraroAPI $horaro;

    /** @var string */
    protected string $timeZone = 'Europe/Paris';

    /** @var string|null */
    private ?string $error = null;

    /** @var array */
    public array $assign = [];

    /**
     * Module initialization
     * Here everything is done while the module starts
     *
     * @return void
     * @throws Exception
     */
    public function init()
    {
        if (!empty($this->settings('events.slug'))) {
            $this->baseSlug = ltrim($this->settings('events.slug'), '/');
        }

        if (!empty($this->settings('events.event_slug'))) {
            $this->eventSlug = ltrim($this->settings('events.event_slug'), '/');
        }

        $this->moduleDirectory = MODULES . '/events';
        $this->core->addCSS(url($this->moduleDirectory . '/assets/css/style.css'));
        
        $this->horaro = new HoraroAPI();
        $this->horaro->setErrorHandler([$this, 'onHoraroError']);

        $this->tpl->set('eventsBaseSlug', $this->baseSlug);
        $this->tpl->set('eventsEventSlug', $this->eventSlug);
        $this->tpl->set('upcomingEvents', $this->getUpcomingEvents());
    }

    /**
     * Register module routes
     * Call the appropriate method/function based on URL
     *
     * @return void
     * @throws Exception
     */
    public function routes()
    {
        $this->route($this->baseSlug, 'getIndex');
        $this->route($this->baseSlug . '/' . $this->eventSlug . '/(:str)', 'getEvent');
        $this->route($this->baseSlug . '/' . $this->eventSlug . '/(:str)/' . $this->icalName . '.ics', 'getIcalExport');
        $this->route($this->baseSlug . '/' . $this->icalName . '.ics', 'getIcalAllExport');
    }

    /**
     * GET: /index
     * Display main page including calendar
     *
     * @throws Exception
     */
    public function getIndex()
    {
        $events = $this->getEventSourcesJson();

        $this->assign['locale'] = $this->getCurrentLang();
        $this->assign['eventSources'] = addcslashes(json_encode($events), "'");
        $this->assign['time_zone'] = $this->timeZone;
        $this->assign['ical_all_href'] =  '/' . $this->baseSlug . '/' . $this->icalName . '.ics';

        if (!is_null($this->error)) {
            $this->notify('failure', $this->error);
        }

        $page = [
            'title' => $this->lang('title'),
            'desc' => $this->lang('desc'),
            'content' => $this->draw('events.html', $this->assign)
        ];

        $this->setTemplate('index.html');
        $this->core->addCSS(url($this->moduleDirectory . '/css/events.css'));
        $this->tpl->set('page', $page);
    }

    /**
     * GET: /planning/event/(:id)
     *
     * Display event page including event details
     * @param int|null $eventId
     * @return void
     * @throws Exception
     */
    public function getEvent(int $eventId = null)
    {
        if (!empty($eventId)) {
            $this->assign['locale'] = $this->getCurrentLang();
            $this->assign['event'] = $this->getEventDetails($eventId);
            $this->assign['hasMap'] = false;
            $this->assign['calendar_url'] = url($this->baseSlug);
            $this->assign['time_zone'] = $this->timeZone;

            if (!empty($this->assign['event'])) {
                $event = $this->assign['event'];

                if (!is_null($this->error)) {
                    $this->notify('failure', $this->error);
                }

                if (!empty($event['latitude']) && !empty($event['longitude'])) {
                    $this->assign['hasMap'] = true;
                }

                $page = [
                    'title' => $this->assign['event']['database_name'],
                    'desc' => $this->lang('event_start') . ' ' . $this->assign['event']['database_start'],
                    'content' => $this->draw('event.details.html', $this->assign)
                ];
            } else {
                $page = [
                    'title' => $this->lang('title'),
                    'desc' => $this->lang('desc'),
                    'content' => $this->lang('event_not_available')
                ];
            }

            $this->setTemplate('index.html');
            $this->core->addCSS(url(MODULES . '/events/css/events.css'));
            $this->tpl->set('page', $page);
        } else {
            return $this->core->module->pages->get404();
        }
    }

    /**
     * GET: /planning/event/(:id)/$this->icalName.ics
     *
     * Generate an iCal file for one or many events
     * NB : generated only this event if not linked to an Horaro event
     * (because else, "event details" page display a direct link to Horaro iCal file)
     *
     * @param array|int $eventIds
     * @return string
     * @throws Exception
     */
    public function getIcalExport($eventIds): string
    {
        $icalGenerated = false;
        $events = [];
        $calendarComponent = '';

        if (is_numeric($eventIds)) {
            $eventIds = [$eventIds];
        }

        if (!empty($eventIds)) {
            foreach ($eventIds as $eventId) {
                $this->assign['locale'] = $this->getCurrentLang();
                $this->assign['event'] = $this->getEventDetails($eventId);
                $this->assign['time_zone'] = $this->timeZone;
                
                $parsedIcalUrl = parse_url($this->assign['event']['ical_url']);
                $parsedHoraroUrl = parse_url(self::DEFAULT_HORARO_URL);

                if ($parsedIcalUrl['host'] !== $parsedHoraroUrl['host']) {
                    // 1. Create Event domain entity
                    $event = new Event();

                    $dateTimeStart = new DateTimeImmutable();
                    $dateTimeEnd = new DateTimeImmutable();
                    $start = new DateTime(
                        $dateTimeStart->setTimestamp($this->assign['event']['database_start_raw']),
                        false
                    );
                    $end = new DateTime(
                        $dateTimeEnd->setTimestamp($this->assign['event']['database_end_raw']),
                        false
                    );
                    $occurrence = new TimeSpan($start, $end);

                    $urlAttachment = null;
                if (isset($this->assign['event']['picture'])) {
                    $urlAttachment = new Attachment(
                        new Uri(url(UPLOADS . "/events/" . $this->assign['event']['picture'])),
                        mime_content_type(UPLOADS . "/events/" . $this->assign['event']['picture'])
                    );
                }

                    $event
                        ->setSummary($this->assign['event']['database_name'])
                        ->setDescription($this->assign['event']['description'])
                        ->setOccurrence($occurrence);
                if (
                        isset($this->assign['event']['latitude']) && isset($this->assign['event']['longitude'])
                        && !empty($this->assign['event']['latitude']) && !empty($this->assign['event']['latitude'])
                ) {
                    $geolocation = new GeographicPosition(
                        $this->assign['event']['latitude'],
                        $this->assign['event']['longitude']
                    );
                    $event->setLocation(
                        (new Location(
                            $this->assign['event']['building_address'] ?? '',
                            $this->assign['event']['building_name'] ?? ''
                        ))->withGeographicPosition($geolocation)
                    );
                }

                if (isset($urlAttachment)) {
                    $event->addAttachment($urlAttachment);
                }

                    $events[] = $event;
                }
            }

            // 2. Create Calendar domain entity
            $calendar = new Calendar($events);

            // 3. Transform domain entity into an iCalendar component
            $componentFactory = new CalendarFactory();
            $calendarComponent = $componentFactory->createCalendar($calendar);

            $icalGenerated = true;
        }

        if ($icalGenerated) {
            // 4. Set headers
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $this->icalName . '.ics"');
            // 5. Output
            echo $calendarComponent;
            // Bonus : Prevent BatFlat to insert its default HTML template into export. Cleaner and lighter.
            exit();
        } else {
            return $this->core->module->pages->get404();
        }
    }

    /**
     * GET: /planning/event/$this->icalName.ics
     *
     * Generate an iCal file all upcoming events
     * NB : generated only this event if not linked to an Horaro event
     * (because else, "event details" page display a direct link to Horaro iCal file)
     *
     * @return string
     * @throws Exception
     */
    public function getIcalAllExport(): ?string
    {
        $eventIds = [];
        $upcomingEvents = $this->getUpcomingEvents();

        foreach ($upcomingEvents as $event) {
            $eventIds[] = $event['id'];
        }

        if (count($eventIds)) {
            return $this->getIcalExport($eventIds);
        } else {
            return $this->core->module->pages->get404();
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function getCurrentLang(): string
    {
        $lang = $_SESSION['lang'];
        if (!$lang) {
            $lang = $this->settings('settings', 'lang_site');
        }

        return mb_substr($lang, 0, 2);
    }

    /**
     * @throws Exception
     */
    protected function getEventSourcesJson(): array
    {
        $events = [];
        $eventSources = [];

        try {
            $schedules = $this->getEvents();
            foreach ($schedules as $schedule) {
                $date = new DateTimeImmutable();
                $date->setTimezone(new DateTimeZone($this->timeZone));
                $eventStart = $date->setTimestamp($schedule['start_at'])->format('Y-m-d\TH:i:s');
                $eventEnd = $date->setTimestamp($schedule['end_at'])->format('Y-m-d\TH:i:s');

                $event = [
                    'id' => $schedule['id'],
                    'title' => $schedule['name'],
                    'start' => $eventStart,
                    'end' => $eventEnd,
                    'url' => url($this->baseSlug . '/' . $this->eventSlug . '/' . $schedule['id']),
                    'display' => 'block'
                ];

                if (!isset($events[$schedule['group_id']])) {
                    $events[$schedule['group_id']] = [];
                }

                $events[$schedule['group_id']][] = $event;
            }
        } catch (Exception $e) {
            $this->error = $this->lang('schedule_not_available');
        }

        // Setting event source objects from groups
        $groups = $this->db('events_groups')->where('lang', $_SESSION['lang'])->toArray();

        foreach ($groups as $group) {
            $eventSources[] = [
                'events' => array_values($events[$group['id']] ?? []),
                'color' => $group['color'],
                'textColor' => $group['textColor']
            ];
        }

        return $eventSources;
    }

    /**
     * Get events from database
     *
     * @param array $arrayIds
     * @return array
     */
    protected function getEvents(array $arrayIds = []): array
    {
        $fields = [
            'id',
            'name',
            'start_at',
            'end_at',
            'description',
            'picture',
            'group_id',
            'building_name',
            'building_address',
            'latitude',
            'longitude',
            'channel_name',
            'horaro_event_id',
            'horaro_schedule_id',
            'lang',
            'markdown',
            'registration'
        ];

        if (empty($arrayIds)) {
            $rows = $this->db('events')
                ->where('lang', $_SESSION['lang'])
                ->where('published_at', '<=', time())
                ->select($fields)
                ->toArray();
        } else {
            $rows = $this->db('events')
                ->where(function ($qb) use ($arrayIds) {
                    foreach ($arrayIds as $arrayId) {
                        $qb->where('id', '=', $arrayId, 'OR');
                    }
                })
                ->where('lang', $_SESSION['lang'])
                ->where('published_at', '<=', time())
                ->select($fields)
                ->toArray();
        }
        
        foreach ($rows as &$row) {
            $row['horaro_url'] =
                self::DEFAULT_HORARO_URL . '/' . $row['horaro_event_id'] . '/' . $row['horaro_schedule_id'];
        }

        return $rows;
    }

    /**
     * @param string $eventId
     * @return array
     */
    protected function getEventDetails(string $eventId): array
    {
        $event = [];

        try {
            $schedules = $this->getSchedulesData([$eventId]);

            if (empty($schedules)) {
                throw new Exception('No event found.');
            }
            $schedule = $schedules[0];

             if (property_exists($schedule, 'horaro_url')) {
                $icalUrl = $schedule->horaro_url . '.ical';
            } else {
                $icalUrl = url(
                    $this->baseSlug . '/' . $this->eventSlug . '/' . $eventId . '/' . $this->icalName . '.ics'
                );
            }

            $event = [
                'id' => $eventId,
                'database_name' => $schedule->database_name,
                'database_start' => date($this->lang('datetime_format'), $schedule->database_start),
                'database_start_raw' => $schedule->database_start,
                'database_end_raw' => $schedule->database_end,
                'description' => $schedule->database_description,
                'picture' => $schedule->database_picture,
                'building_name' => $schedule->database_building_name,
                'building_address' => $schedule->database_building_address,
                'latitude' => $schedule->database_latitude,
                'longitude' => $schedule->database_longitude,
                'horaro_id' => property_exists($schedule, 'id') ? $schedule->id : null,
                'horaro_name' => property_exists($schedule, 'name') ? $schedule->name : null,
                'horaro_start' => property_exists($schedule, 'twitch') ? date(
                    $this->lang('date_format'),
                    strtotime($schedule->start)
                ) : null,
                'website' => property_exists($schedule, 'website') ? $schedule->website : null,
                'channel_name' => property_exists(
                    $schedule,
                    'twitch'
                ) ? $schedule->twitch : $schedule->database_channel,
                'ical_url' => $icalUrl,
                'items' => [],
                // Only displaying the first 3 columns of data from Horaro
                'columns' => isset($schedule->columns) ? array_slice($schedule->columns, 0, 3) : []
            ];
            $scheduleItems = $schedule->items ?? [];
            $currentDate = null;

            foreach ($scheduleItems as $item) {
                // Date conversions
                $date = new DateTimeImmutable();
                $date->setTimestamp($item->scheduled_t + $item->length_t);
                $end = $date->format($this->lang('time_format'));

                // Handle date changes
                $scheduledDate = date($this->lang('date_format'), $item->scheduled_t);
                if ($scheduledDate != $currentDate) {
                    $intlDateFormatter = IntlDateFormatter::create(
                        null,
                        IntlDateFormatter::FULL,
                        IntlDateFormatter::NONE,
                        null,
                        null
                    );
                    $event['items'][] = [
                        'text' => ucfirst($intlDateFormatter->format($item->scheduled_t))
                    ];

                    $currentDate = $scheduledDate;
                }

                // URL conversions
                foreach ($item->data as &$data) {
                    preg_match_all('#\[([^\]]*)\]\(([^\)]*)\)#', $data, $matches, PREG_SET_ORDER);

                    foreach ($matches as $match) {
                        $markdown = sprintf('[%s](%s)', $match[1], $match[2]);
                        $html = sprintf('<a href="%s" target="_blank">%s</a>', $match[2], $match[1]);
                        $data = str_replace($markdown, $html, $data);
                    }
                }

                $event['items'][] = [
                    'columns' => array_slice($item->data, 0, 3), // Only taking the first 3 columns to display
                    'start' => date($this->lang('time_format'), $item->scheduled_t),
                    'end' => $end
                ];
            }
        } catch (Exception $e) {
            $this->error = $this->lang('event_not_available');
        }

        return $event;
    }

    /**
     * Return schedules data used to display FO calendar
     *
     * @param array $eventIds
     * @return array
     */
    protected function getSchedulesData(array $eventIds = []): array
    {
        $scheduleData = [];
        $events = $this->getEvents($eventIds);

        foreach ($events as $key => $event) {
            $scheduleData[$key] = new stdClass();
            
            $schedule = $this->horaro->getSchedule($event['horaro_schedule_id'], $event['horaro_event_id']);
            if (!empty($schedule)) {
                $scheduleData[$key] = $schedule;
            }

            $scheduleData[$key]->database_id = $event['id'];
            $scheduleData[$key]->database_name = $event['name'];
            $scheduleData[$key]->database_description = $event['description'];
            $scheduleData[$key]->database_picture = $event['picture'];
            $scheduleData[$key]->database_building_name = $event['building_name'];
            $scheduleData[$key]->database_building_address = $event['building_address'];
            $scheduleData[$key]->database_latitude = $event['latitude'];
            $scheduleData[$key]->database_longitude = $event['longitude'];
            $scheduleData[$key]->database_start = $event['start_at'];
            $scheduleData[$key]->database_end = $event['end_at'];
            $scheduleData[$key]->database_channel = $event['channel_name'];
            if (!empty($event['horaro_event_id']) && !empty($event['horaro_schedule_id'])) {
                $scheduleData[$key]->horaro_url =
                    self::DEFAULT_HORARO_URL . '/' . $event['horaro_event_id'] . '/' . $event['horaro_schedule_id'];
            }
        }

        return $scheduleData;
    }

    /**
     * Gets the upcoming events' basic details.
     *
     * @return array The upcoming events as an array.
     */
    public function getUpcomingEvents(): array
    {
        $rows = $this->db('events')
                ->where('lang', $_SESSION['lang'])
                ->where('published_at', '<=', time())
                ->where('end_at', '>=', time())
                ->asc('start_at')
                ->toArray();

        foreach ($rows as &$row) {
            $row['start_at_short'] = date($this->lang('date_format_short'), $row['start_at']);
        }

        return $rows;
    }
}
