<?php

/**
 * This file is part of Batflat ~ the lightweight, fast and easy CMS
 *
 * @author       Yohann Lorant
 */

namespace Inc\Modules\Events_Registration;

use Exception;
use Inc\Core\AdminModule;
use Inc\Core\Lib\Pagination;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception as CsvException;
use League\Csv\Writer;

/**
 * Events registration admin class
 */
class Admin extends AdminModule
{
    public array $assign = [];

    /**
     * Module navigation
     * Items of the returned array will be displayed in the administration sidebar
     *
     * @return array
     */
    public function navigation(): array
    {
        return [
            $this->lang('manage', 'general') => 'manage',
            $this->lang('settings', 'general') => 'settings'
        ];
    }

    /**
     * GET: /admin/events_registration/manage
     * List of events registration, including delete and export calls
     *
     * @param int $page
     * @return string
     * @throws Exception
     */
    public function anyManage(int $page = 1): string
    {
        // Mass delete action
        if (isset($_POST['delete'])) {
            if (isset($_POST['registration-list']) && !empty($_POST['registration-list'])) {
                foreach ($_POST['registration-list'] as $item) {
                    if ($this->db('events_registration')->delete($item) === 1) {
                        $this->notify('success', $this->lang('delete_success'));
                    } else {
                        $this->notify('failure', $this->lang('delete_failure'));
                    }
                }

                redirect(url([ADMIN, 'events_registration', 'manage']));
            }
        }

        // Export action
        if (isset($_POST['export'])) {
            if (isset($_POST['registration-list']) && !empty($_POST['registration-list'])) {
                $registrations = $this->db('events_registration')
                    ->select([
                        'events_registration.runner_name',
                        'events.name AS `event_name`',
                        'events_registration.game_name',
                        'events_registration.game_category',
                        'events_registration.estimated_time',
                        'events_registration.race',
                        'events_registration.race_opponents',
                        'events_registration.comment'
                    ])
                    ->leftJoin('events', 'events_registration.event_id = events.id')
                    ->in('events_registration.id', $_POST['registration-list'])
                    ->toArray();

                $this->generateCSVExport($registrations);
            }
        }

        // Pagination
        $totalRecords = count($this->db('events_registration')->toArray());
        $pagination = new Pagination($page, $totalRecords, 10, url([ADMIN, 'events_registration', 'manage', '%d']));
        $this->assign['pagination'] = $pagination->nav();

        // List
        $this->assign['registrationCount'] = 0;
        $rows = $this->db('events_registration')
            ->leftJoin('events', 'events.id = events_registration.event_id')
            ->limit($pagination->offset() . ', ' . $pagination->getRecordsPerPage())
            ->select([
                'events_registration.id',
                'events_registration.runner_name',
                'events_registration.game_name',
                'events_registration.game_category',
                'events_registration.estimated_time',
                'events_registration.race',
                'events_registration.race_opponents',
                'events_registration.status',
                'events_registration.event_id',
                'events_registration.created_at',
                'events.name',
                'events.start_at',
            ])
            ->asc('events_registration.created_at')
            ->toArray();

        $this->assign['registrations'] = [];
        if ($totalRecords) {
            $this->assign['registrationCount'] = $totalRecords;
            foreach ($rows as $row) {
                $row['created_at'] = date('d-m-Y H:i:s', $row['created_at']);
                $row['estimated_time'] = $this->getEstimatedTimeText($row['estimated_time']);
                $row['race'] ?
                    $row['race'] = $this->lang('say_yes', 'general') : $row['race'] = $this->lang('say_no', 'general');
                $row['event'] = '-';
                if ($row['event_id']) {
                    if(!empty($row['name'])) {
                        $row['event'] = $row['name'] . ' (' . date('d-m-Y', $row['start_at']) . ')';
                    } else {
                        $row['event'] = $this->lang('deleted_event', 'events_registration');
                    }
                }
                $row['editURL'] = url([ADMIN, 'events_registration', 'edit', $row['id']]);
                $row['delURL'] = url([ADMIN, 'events_registration', 'delete', $row['id']]);

                $this->assign['registrations'][] = $row;
            }
        }

        return $this->draw('manage.html', ['registrations' => $this->assign]);
    }

    /**
     * Edit a registration
     * (or display null data to create a new one)
     *
     * @param int|null $id
     * @return string|void
     * @throws Exception
     */
    public function getEdit(int $id = null)
    {
        // lang
        if (!empty($_GET['lang'])) {
            $lang = $_GET['lang'];
            $_SESSION['events']['last_lang'] = $lang;
        } else {
            if (!empty($_SESSION['events']['last_lang'])) {
                $lang = $_SESSION['events']['last_lang'];
            } else {
                $lang = $this->settings('settings.lang_site');
            }
        }

        $this->assign['manageURL'] = url([ADMIN, 'events_registration', 'manage']);
        $this->assign['editor'] = $this->settings('settings', 'editor');
        $this->addHeaderFiles();

        if ($id === null) {
            $eventRegistration = [
                'id' => null,
                'runner_name' => '',
                'game_name' => '',
                'game_category' => '',
                'estimated_time' => 0,
                'race' => 0,
                'race_opponents' => '',
                'event_id' => null,
                'registration' => 0,
                'comment' => ''
            ];
        } else {
            $eventRegistration = $this->db('events_registration')
                ->where('id', $id)
                ->oneArray();
            $eventRegistration['estimated_time_text'] = $this->getEstimatedTimeText(
                $eventRegistration['estimated_time']
            );
        }

        $this->assign['events'] = $this->getAvailableEvents($eventRegistration['event_id'] ?: null, $lang);

        if (!empty($eventRegistration)) {
            $this->assign['form'] = htmlspecialchars_array($eventRegistration);

            $this->assign['title'] = ($eventRegistration['id'] === null) ?
                $this->lang('new_registration') : $this->lang('edit_registration');

            return $this->draw('registration.form.html', ['registration' => $this->assign]);
        } else {
            redirect(url([ADMIN, 'events_registration', 'manage']));
        }
    }

    /**
     * Advanced export, to allow exporting all submissions relative to a given event (or everything).
     * @return void 
     */
    public function anyExport()
    {
        // lang
        if (!empty($_GET['lang'])) {
            $lang = $_GET['lang'];
            $_SESSION['events']['last_lang'] = $lang;
        } else {
            if (!empty($_SESSION['events']['last_lang'])) {
                $lang = $_SESSION['events']['last_lang'];
            } else {
                $lang = $this->settings('settings.lang_site');
            }
        }

        if(isset($_POST['export'])) {
            $qb = $this->db('events_registration')
                ->select([
                    'events_registration.runner_name',
                    'events.name AS `event_name`',
                    'events_registration.game_name',
                    'events_registration.game_category',
                    'events_registration.estimated_time',
                    'events_registration.race',
                    'events_registration.race_opponents',
                    'events_registration.comment'
                ])
                ->leftJoin('events', 'events_registration.event_id = events.id');
            
            if(!empty($_POST['event'])) {
                $eventId = intval($_POST['event']);
                if($eventId == -1) {
                    $qb->isNull('event_id');
                } else {
                    $qb->where('event_id', $eventId);
                }
            }

            $eventRegistration = $qb->toArray();

            $this->generateCSVExport($eventRegistration);
        }
        
        $this->assign['events'] = $this->getAvailableEvents(null, $lang);
        
        return $this->draw('export.html', ['export' => $this->assign]);
    }

    /**
     * POST: /admin/events_registration/save/(:id)
     *
     * @param int|null $id
     */
    public function postSave(int $id = null)
    {
        unset($_POST['save']);

        // redirect location
        if (!$id) {
            $location = url([ADMIN, 'events_registration', 'add']);
        } else {
            $location = url([ADMIN, 'events_registration', 'edit', $id]);
        }

        if (checkEmptyFields(['runner_name', 'game_name', 'game_category', 'estimated_time', 'duration'], $_POST)) {
            $this->notify('failure', $this->lang('empty_inputs', 'general'));
            $this->assign['form'] = htmlspecialchars_array($_POST);
            redirect($location);
        }

        if (empty($id) || $id < 0) {
            $id = 0;
        }

        // format conversion date
        $_POST['estimated_time'] = $_POST['duration'];
        unset($_POST['duration']);

        if (!isset($_POST['race'])) {
            $_POST['race'] = 0;
        }

        if (!$id) {
            $query = $this->db('events_registration')->save($_POST);
            $location = url([ADMIN, 'events_registration', 'edit', $this->db()->pdo()->lastInsertId()]);
        } else {
            // edit
            $query = $this->db('events_registration')->where('id', $id)->save($_POST);
        }

        if ($query) {
            $this->notify('success', $this->lang('save_success'));
        } else {
            $this->notify('failure', $this->lang('save_failure'));
        }

        redirect($location);
    }

    /**
     * GET: /admin/events_registration/settings
     * Manage Events module general configuration
     *
     * @return string
     * @throws Exception
     */
    public function getSettings(): string
    {
        $this->assign['editor'] = $this->settings('settings.editor');
        $this->addHeaderFiles();

        $value = $this->settings('events_registration');

        $this->assign['slug'] = $value['slug'] ?? "";
        $this->assign['description'] = $value['description'] ?? "";

        return $this->draw('settings.html', ['events_registration' => $this->assign]);
    }

    /**
     *
     */
    public function postSaveSettings()
    {
        $update = [
            'slug' => $_POST['slug'],
            'description' => $_POST['description'],
        ];

        $errors = 0;
        foreach ($update as $field => $value) {
            $saved = $this->db('settings')
                ->where('module', 'events_registration')
                ->where('field', $field)
                ->save(['value' => $value]);
            
            if (!$saved) {
                $errors++;
            }
        }

        if (!$errors) {
            $this->notify('success', $this->lang('save_settings_success'));
        } else {
            $this->notify('failure', $this->lang('save_settings_failure'));
        }

        redirect(url([ADMIN, 'events_registration', 'settings']));
    }

    public function getJavascript()
    {
        header('Content-type: text/javascript');
        echo $this->draw(MODULES . '/events_registration/js/admin/events_registration.js');
        exit();
    }

    /**
     * Generates a CSV export from the given registrations.
     * 
     * @param mixed $registrations 
     * @return never 
     * @throws CannotInsertRecord 
     * @throws CsvException 
     */
    public function generateCSVExport($registrations)
    {
        foreach ($registrations as $registration) {
            $registration['estimated_time'] = $this->getEstimatedTimeText($registration['estimated_time']);
            $registration['race'] ?
                $registration['race'] = $this->lang('say_yes', 'general') : $row['race'] = $this->lang(
                    'say_no',
                    'general'
                );
        }

        $header = array_keys(reset($registrations));
        $csv = Writer::createFromString();
        $csv->insertOne($header);
        $csv->insertAll($registrations);

        header('Content-Disposition: attachment; filename="export.csv"');
        header("Content-Type: text/csv");
        echo $csv->toString();
        exit();
    }

    /**
     * Return estimated time into human-readable format
     *
     * @param int $duration
     * @return string
     */
    private function getEstimatedTimeText(int $duration): string
    {
        return sprintf(
            "%02d%s%02d%s%02d%s",
            floor($duration / 3600),
            'h ',
            ($duration / 60) % 60,
            'm ',
            $duration % 60,
            's'
        );
    }

    /**
     * Get available events AND event previously selected if defined
     * (in case this event is not available anymore but previously chosen)
     *
     * @param int|null $previousEventSelectedId
     * @param string $lang
     * @return array
     */
    private function getAvailableEvents(int $previousEventSelectedId = null, string $lang = ''): array
    {
        $fields = [
            'id',
            'name',
            'start_at',
            'end_at'
        ];

        return $this->db('events')
            ->where('lang', $lang)
            ->where('registration', 1)
            ->orWhere('id', $previousEventSelectedId)
            ->select($fields)
            ->toArray();
    }

    protected function addHeaderFiles()
    {
        parent::addHeaderFiles();

        // MODULE SCRIPTS
        $this->core->addJS(url([ADMIN, 'events_registration', 'javascript']));
        // MODULE CSS
        $this->core->addCSS(url(MODULES . '/events_registration/css/admin/events_registration.css'));
    }
}
