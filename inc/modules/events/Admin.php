<?php

/**
 * This file is part of Batflat ~ the lightweight, fast and easy CMS
 *
 * @author       Yohann Lorant
 */

namespace Inc\Modules\Events;

use Exception;
use Inc\Core\AdminModule;
use Inc\Core\Lib\Pagination;

/**
 * Events admin class
 */
class Admin extends AdminModule
{
    public $assign = [];

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
            $this->lang('add_new') => 'add',
            $this->lang('manage_groups') => 'manageGroups',
            $this->lang('add_group') => 'addGroup',
            $this->lang('settings', 'general') => 'settings'
        ];
    }

    /**
     * GET: /admin/events/manage
     * List of events, including delete call
     *
     * @param int $page
     * @return string
     * @throws Exception
     */
    public function anyManage(int $page = 1): string
    {
        if (isset($_POST['delete'])) {
            if (isset($_POST['event-list']) && !empty($_POST['event-list'])) {
                foreach ($_POST['event-list'] as $item) {
                    $row = $this->db('events')->where('id', $item)->oneArray();
                    if ($this->db('events')->delete($item) === 1) {
                        if (!empty($row['picture']) && file_exists(UPLOADS . "/events/" . $row['picture'])) {
                            unlink(UPLOADS . "/events/" . $row['picture']);
                        }

                        $this->notify('success', $this->lang('delete_success'));
                    } else {
                        $this->notify('failure', $this->lang('delete_failure'));
                    }
                }

                redirect(url([ADMIN, 'events', 'manage']));
            }
        }

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

        // Slugs
        $baseSlug = $this->settings('events.slug') ? ltrim($this->settings('events.slug'), '/') : Site::DEFAULT_SLUG;
        $eventSlug = $this->settings('events.event_slug') ? ltrim($this->settings('events.event_slug'), '/') : Site::DEFAULT_EVENT_SLUG;

        // pagination
        $totalRecords = count($this->db('events')->where('lang', $lang)->toArray());
        $pagination = new Pagination($page, $totalRecords, 10, url([ADMIN, 'events', 'manage', '%d']));
        $this->assign['pagination'] = $pagination->nav();

        // list
        $this->assign['newURL'] = url([ADMIN, 'events', 'add']);
        $this->assign['eventCount'] = 0;
        $rows = $this->db('events')
            ->where('lang', $lang)
            ->limit($pagination->offset() . ', ' . $pagination->getRecordsPerPage())
            ->desc('start_at')->desc('end_at')
            ->toArray();

        $this->assign['events'] = [];
        if ($totalRecords) {
            $this->assign['eventCount'] = $totalRecords;
            foreach ($rows as $row) {
                $row['start_at'] = date('d-m-Y H:i', $row['start_at']);
                $row['end_at'] = date('d-m-Y H:i', $row['end_at']);
                $row['published_at'] = date("d-m-Y H:i", $row['published_at']);

                $row['editURL'] = url([ADMIN, 'events', 'edit', $row['id']]);
                $row['delURL'] = url([ADMIN, 'events', 'delete', $row['id']]);
                $row['viewURL'] = url([$baseSlug, $eventSlug, $row['id']]);

                $this->assign['events'][] = $row;
            }
        }

        $this->assign['langs'] = $this->getLanguages($lang);

        return $this->draw('manage.html', ['events' => $this->assign]);
    }

    /**
     * GET: /admin/events/manageGroups
     * List of event groups, including delete call
     * @param int $page
     * @return string
     * @throws Exception
     */
    public function anyManageGroups(int $page = 1): string
    {

        if (isset($_POST['delete'])) {
            if (isset($_POST['group-list']) && !empty($_POST['group-list'])) {
                foreach ($_POST['group-list'] as $item) {
                    $row = $this->db('events_groups')->where('id', $item)->oneArray();
                    if ($this->db('events_groups')->delete($item) === 1) {
                        $this->notify('success', $this->lang('delete_group_success'));
                    } else {
                        $this->notify('failure', $this->lang('delete_group_failure'));
                    }
                }

                redirect(url([ADMIN, 'events', 'manage']));
            }
        }

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

        // pagination
        $totalRecords = count($this->db('events_groups')->where('lang', $lang)->toArray());
        $pagination = new Pagination($page, $totalRecords, 10, url([ADMIN, 'events_groups', 'manageGroups', '%d']));
        $this->assign['pagination'] = $pagination->nav();

        // list
        $this->assign['newURL'] = url([ADMIN, 'events', 'addGroup']);
        $this->assign['groupCount'] = $totalRecords;
        $rows = $this->db('events_groups')
            ->where('lang', $lang)
            ->limit($pagination->offset() . ', ' . $pagination->getRecordsPerPage())
            ->toArray();

        if ($totalRecords) {
            foreach ($rows as &$row) {
                $row['editURL'] = url([ADMIN, 'events', 'editGroup', $row['id']]);
            }
        }

        $this->assign['groups'] = $rows;

        $this->assign['langs'] = $this->getLanguages($lang);

        return $this->draw('manage.groups.html', ['groups' => $this->assign]);
    }

    /**
     * @return string|void
     * @throws Exception
     */
    public function getAddGroup()
    {
        return $this->getEditGroup(null);
    }

    /**
     * @param null $id
     * @return string|void
     * @throws Exception
     */
    public function getEditGroup($id = null)
    {
        $this->assign['manageURL'] = url([ADMIN, 'events', 'manageGroups']);
        $this->addHeaderFiles();

        if ($id === null) {
            $group = [
                'id' => null,
                'name' => '',
                'color' => '',
                'textColor' => '',
                'lang' => $this->settings('settings.lang_site')
            ];
        } else {
            $group = $this->db('events_groups')->where('id', $id)->oneArray();
        }


        if (!empty($group)) {
            $this->assign['langs'] = $this->getLanguages($group['lang'], 'selected');
            $this->assign['form'] = htmlspecialchars_array($group);
            $this->assign['title'] = ($group['id'] === null) ? $this->lang('new_group') : $this->lang('edit_group');

            return $this->draw('group.form.html', ['group' => $this->assign]);
        } else {
            redirect(url([ADMIN, 'events', 'manageGroups']));
        }
    }

    /**
     * Add a new event
     * (based on "edit an event" but with null data)
     *
     * @throws Exception
     */
    public function getAdd()
    {
        return $this->getEdit(null);
    }

    /**
     * Edit an event
     * (or display null data to create a new one)
     *
     * @param int|null $id
     * @return string|void
     * @throws Exception
     */
    public function getEdit(int $id = null)
    {
        $this->assign['manageURL'] = url([ADMIN, 'events', 'manage']);
        $this->assign['pictureDeleteURL'] = url([ADMIN, 'events', 'deletePicture', $id]);
        $this->assign['editor'] = $this->settings('settings', 'editor');
        $this->addHeaderFiles();

        if ($id === null) {
            $event = [
                'id' => null,
                'name' => '',
                'start_at' => strtotime('now'),
                'end_at' => strtotime('+1 hour'),
                'description' => '',
                'picture' => null,
                'group_id' => null,
                'building_name' => null,
                'building_address' => null,
                'latitude' => null,
                'longitude' => null,
                'channel_name' => '',
                'horaro_url' => null,
                'lang' => $this->settings('settings.lang_site'),
                'markdown' => 0,
                'registration' => 0,
                'published_at' => time(),
            ];
        } else {
            $event = $this->db('events')->where('id', $id)->oneArray();
            $event['horaro_url'] = null;
            if (isset($event['horaro_event_id']) && isset($event['horaro_schedule_id'])) {
                $event['horaro_url'] = $this->getHoraroUrl($event['horaro_event_id'], $event['horaro_schedule_id']);
            }
        }

        $groups = $this->db('events_groups')->asc('name')->toArray();

        foreach ($groups as &$group) {
            $group['attr'] = "";
            if ($event['group_id'] == $group['id']) {
                $group['attr'] .= 'selected';
            }
        }

        $this->assign['groups'] = $groups;

        if (!empty($event)) {
            $this->assign['langs'] = $this->getLanguages($event['lang'], 'selected');
            $this->assign['form'] = htmlspecialchars_array($event);
            $this->assign['form']['description'] = $this->tpl->noParse($this->assign['form']['description']);
            $this->assign['form']['start_at'] = date('Y-m-d\TH:i', $event['start_at']);
            $this->assign['form']['end_at'] = date('Y-m-d\TH:i', $event['end_at']);
            $this->assign['form']['published_at'] = date("Y-m-d\TH:i", $event['published_at']);

            $this->assign['title'] = ($event['id'] === null) ? $this->lang('new_event') : $this->lang('edit_event');

            return $this->draw('event.form.html', ['event' => $this->assign]);
        } else {
            redirect(url([ADMIN, 'events', 'manage']));
        }
    }

    /**
     * GET: /admin/events/settings
     * Manage Events module general configuration
     *
     * @return string
     * @throws Exception
     */
    public function getSettings()
    {
        $value = $this->settings('events');

        $assign['slug'] = $value['slug'];
        $assign['event_slug'] = $value['event_slug'];

        return $this->draw('settings.html', ['events' => $assign]);
    }

    /**
     * POST: /admin/events/save/(:id)
     *
     * @param int|null $id
     */
    public function postSave(int $id = null)
    {
        unset($_POST['save'], $_POST['files']);

        // redirect location
        if (!$id) {
            $location = url([ADMIN, 'events', 'add']);
        } else {
            $location = url([ADMIN, 'events', 'edit', $id]);
        }

        if (checkEmptyFields(['name', 'description', 'start_at', 'end_at'], $_POST)) {
            $this->notify('failure', $this->lang('empty_inputs', 'general'));
            $this->assign['form'] = htmlspecialchars_array($_POST);
            $this->assign['form']['description'] = $this->tpl->noParse($this->assign['form']['description']);
            redirect($location);
        }

        if ($id === null) {
            $id = 0;
        }

        // format conversion date
        $_POST['start_at'] = strtotime($_POST['start_at']);
        $_POST['end_at'] = strtotime($_POST['end_at']);
        $_POST['published_at'] = strtotime($_POST['published_at']);
        
        // Horaro URL deconstruct
        if (isset($_POST['horaro_url'])) {
            if (!empty($_POST['horaro_url'])) {
                $ids = $this->getHoraroIds($_POST['horaro_url']);
                $_POST['horaro_event_id'] = $ids[0];
                $_POST['horaro_schedule_id'] = $ids[1];
            }
            unset($_POST['horaro_url']);
        }

        if (!isset($_POST['markdown'])) {
            $_POST['markdown'] = 0;
        }
        
        if (!isset($_POST['registration'])) {
            $_POST['registration'] = 0;
        }

        if (isset($_FILES['picture']['tmp_name'])) {
            $img = new \Inc\Core\Lib\Image();

            if ($img->load($_FILES['picture']['tmp_name'])) {
                $_POST['picture'] = $this->getPictureName($_POST['name']) . '-' . mt_rand(1, 999) . "." . $img->getInfos('type');
            }
        }

        if (!$id) {
            $query = $this->db('events')->save($_POST);
            $location = url([ADMIN, 'events', 'edit', $this->db()->pdo()->lastInsertId()]);
        } else {
            // edit
            $query = $this->db('events')->where('id', $id)->save($_POST);
        }

        if ($query) {
            if (!file_exists(UPLOADS . "/events")) {
                mkdir(UPLOADS . "/events", 0777, true);
            }

            if (!empty($img) && $img->getInfos('width')) {
                $img->save(UPLOADS . "/events/" . $_POST['picture']);
            }

            $this->notify('success', $this->lang('save_success'));
        } else {
            $this->notify('failure', $this->lang('save_failure'));
        }

        redirect($location);
    }

    /**
     * POST: /admin/events/saveGroup/(:id)
     *
     * @param int|null $id
     */
    public function postSaveGroup(int $id = null)
    {
        unset($_POST['save'], $_POST['files']);

        // redirect location
        if (!$id) {
            $location = url([ADMIN, 'events', 'addGroup']);
        } else {
            $location = url([ADMIN, 'events', 'editGroup', $id]);
        }

        if (checkEmptyFields(['name', 'color'], $_POST)) {
            $this->notify('failure', $this->lang('empty_inputs', 'general'));
            $this->assign['form'] = htmlspecialchars_array($_POST);
            redirect($location);
        }

        if ($id === null) {
            $id = 0;
        }

        if (!$id) {
            $query = $this->db('events_groups')->save($_POST);
            $location = url([ADMIN, 'events', 'editGroup', $this->db()->pdo()->lastInsertId()]);
        } else {
            // edit
            $query = $this->db('events_groups')->where('id', $id)->save($_POST);
        }

        if ($query) {
            $this->notify('success', $this->lang('save_success'));
        } else {
            $this->notify('failure', $this->lang('save_failure'));
        }

        redirect($location);
    }

    /**
     * remove post picture
     * @param int $id
     */
    public function getDeletePicture(int $id)
    {
        if ($post = $this->db('events')->where('id', $id)->oneArray()) {
            unlink(UPLOADS . "/events/" . $post['picture']);
            $this->db('events')->where('id', $id)->save(['picture' => null]);
            $this->notify('success', $this->lang('picture_deleted'));

            redirect(url([ADMIN, 'events', 'edit', $id]));
        }
    }

    /**
     *
     */
    public function postSaveSettings()
    {
        $update = [
            'slug' => $_POST['slug'],
            'event_slug' => $_POST['event_slug'],
        ];

        $errors = 0;
        foreach ($update as $field => $value) {
            if (!$this->db('settings')->where('module', 'events')->where('field', $field)->save(['value' => $value])) {
                $errors++;
            }
        }

        if (!$errors) {
            $this->notify('success', $this->lang('save_settings_success'));
        } else {
            $this->notify('failure', $this->lang('save_settings_failure'));
        }

        redirect(url([ADMIN, 'events', 'settings']));
    }
    
    /**
     * Return Horaro URL with Horaro eventId and scheduleId
     *
     * @param string $eventId
     * @param string $scheduleId
     * @return string
     */
    private function getHoraroUrl(string $eventId, string $scheduleId): ?string
    {
        if (!empty($eventId) && !empty($scheduleId)) {
            return Site::DEFAULT_HORARO_URL . '/' . $eventId . '/' . $scheduleId;
        } else {
            return null;
        }
    }

    /**
     * Return Horaro eventID and scheduleId from an Horaro URL
     *
     * @param $url
     * @return array
     */
    private function getHoraroIds($url): array
    {
        $res = explode('/', parse_url($url, PHP_URL_PATH));
        return [$res[1], $res[2]];
    }

    /**
     * Return a string to rename a picture
     * (to be sure to have correct unique name on hard drive)
     *
     * @param $eventName
     * @return string|string[]|null
     */
    private function getPictureName($eventName)
    {
        $pictureName = str_replace(' ', '-', $eventName); // Replaces all spaces with hyphens.
        $pictureName = preg_replace('/[^A-Za-z0-9\-]/', '', $pictureName); // Removes special chars.
        return preg_replace('/-+/', '-', $pictureName); // Replaces multiple hyphens with single one.
    }

    /**
     * image upload from WYSIWYG
     */
    public function postEditorUpload()
    {
        header('Content-type: application/json');
        $dir = UPLOADS . '/events';
        $error = null;

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        if (isset($_FILES['file']['tmp_name'])) {
            $img = new \Inc\Core\Lib\Image();

            if ($img->load($_FILES['file']['tmp_name'])) {
                $imgPath = $dir . '/' . time() . '.' . $img->getInfos('type');
                $img->save($imgPath);
                echo json_encode(['status' => 'success', 'result' => url($imgPath)]);
            } else {
                $error = $this->lang('editor_upload_fail');
            }

            if ($error) {
                echo json_encode(['status' => 'failure', 'result' => $error]);
            }
        }
        exit();
    }

    /**
     * module JavaScript
     */
    public function getJavascript()
    {
        header('Content-type: text/javascript');
        echo $this->draw(MODULES . '/events/js/admin/events.js');
        exit();
    }

    /**
     * @throws Exception
     */
    protected function addHeaderFiles()
    {
        parent::addHeaderFiles();

        // MODULE SCRIPTS
        $this->core->addJS(url([ADMIN, 'events', 'javascript']));
        // MODULE CSS
        $this->core->addCSS(url(MODULES . '/events/css/admin/events.css'));
    }
}
