<?php

namespace Inc\Modules\Members_Sta;

use Exception;
use Inc\Core\AdminModule;
use Inc\Core\Lib\Pagination;

/**
 * members_sta admin class
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
            $this->lang('add_new') => 'newMember',
            $this->lang('settings') => 'settings'
        ];
    }

    /**
     * GET: /admin/members_sta/manage
     * Manage currently registered members
     *
     * @param int $page
     * @return string
     * @throws Exception
     */
    public function getManage(int $page = 1): string
    {
        // lang
        if (!empty($_GET['lang'])) {
            $lang = $_GET['lang'];
            $_SESSION['members_sta']['last_lang'] = $lang;
        } elseif (!empty($_SESSION['members_sta']['last_lang'])) {
            $lang = $_SESSION['members_sta']['last_lang'];
        } else {
            $lang = $this->settings('settings', 'lang_site');
        }
        $this->assign['langs'] = $this->getLanguages($lang, 'active');

        // pagination
        $totalRecords = count($this->db('members_sta')->where('lang', $lang)->toArray());
        $pagination = new Pagination(
            $page,
            $totalRecords,
            10,
            url([ADMIN, 'members_sta', 'manage', '%d'])
        );
        $this->assign['pagination'] = $pagination->nav();

        // list
        $rows = $this->db('members_sta')->where('lang', $lang)
            ->desc('status')
            ->asc('name')
            ->limit($pagination->offset() . ', ' . $pagination->getRecordsPerPage())
            ->toArray();

        $this->assign['member'] = [];
        if (count($rows)) {
            foreach ($rows as $row) {
                if (strlen($row['description']) > 30) {
                    $row['description'] = substr(strip_tags($row['description']), 0, 30) . ' ...';
                }
                if ($row['status']) {
                    $row['status'] = $this->lang('active');
                } else {
                    $row['status'] = $this->lang('inactive');
                }
                $row['editURL'] = url([ADMIN, 'members_sta', 'editMember', $row['id']]);
                $row['delURL'] = url([ADMIN, 'members_sta', 'deleteMember', $row['id']]);

                $this->assign['member'][] = $row;
            }
        }

        return $this->draw('manage.html', ['members' => $this->assign]);
    }

    /**
     * GET: /admin/members_sta/newMember
     * Register a new member
     *
     * @return string
     * @throws Exception
     */
    public function getNewMember(): string
    {
        $this->assign['editor'] = $this->settings('settings', 'editor');
        $this->assign['title'] = $this->lang('add_new');
        $defaultLang = $this->settings('settings.lang_site');

        $this->addHeaderFiles();

        // Unsaved data with failure
        $e = getRedirectData();
        if (!empty($e)) {
            $this->assign['form'] = [
                'name' => isset_or($e['name'], ''),
                'role' => isset_or($e['role'], ''),
                'description' => isset_or($e['description'], ''),
                'pictureUrl' => isset_or($e['pictureUrl'], ''),
                'twitch_handle' => isset_or($e['twitch_handle'], ''),
                'status' => isset_or($e['status'], false),
                'lang' => isset_or($e['lang'], $defaultLang),
                'markdown' => isset_or($e['markdown'], false),
            ];
        } else {
            $this->assign['form'] = [
                'name' => '',
                'role' => '',
                'description' => '',
                'pictureUrl' => url(MODULES . '/members_sta/img/default.png'),
                'twitch_handle' => '',
                'status' => 0,
                'lang' => $defaultLang,
                'markdown' => 0
            ];
        }

        $this->assign['langs'] = $this->getLanguages($this->settings('settings.lang_site'), 'selected');
        $this->assign['manageURL'] = url([ADMIN, 'members_sta', 'manage']);

        return $this->draw('form.member.html', ['member' => $this->assign]);
    }

    /**
     * edit member
     *
     * @param int $id
     * @return string|void
     * @throws Exception
     */
    public function getEditMember(int $id): string
    {
        $this->assign['editor'] = $this->settings('settings', 'editor');
        $this->addHeaderFiles();

        $member = $this->db('members_sta')->where('id', $id)->oneArray();

        if (!empty($member)) {
            // Unsaved data with failure
            if (!empty($e = getRedirectData())) {
                $member = array_merge(
                    $member,
                    ['name' => isset_or($e['name'], ''), 'description' => isset_or($e['description'], '')]
                );
            }

            $this->assign['form'] = htmlspecialchars_array($member);
            $this->assign['form']['description'] = $this->tpl->noParse($this->assign['form']['description']);

            $this->assign['title'] = $this->lang('edit_member');
            $this->assign['langs'] = $this->getLanguages($member['lang'], 'selected');
            if (isset($member['picture'])) {
                $this->assign['form']['pictureUrl'] = url(UPLOADS . '/members_sta/' . $member['picture']);
            } else {
                $this->assign['form']['pictureUrl'] = url(MODULES . '/members_sta/img/default.png');
            }
            $this->assign['manageURL'] = url([ADMIN, 'members_sta', 'manage']);
            $this->assign['pictureDeleteURL'] = url([ADMIN, 'members_sta', 'deletePicture', $id]);

            return $this->draw('form.member.html', ['member' => $this->assign]);
        } else {
            redirect(url([ADMIN, 'members_sta', 'manage']));
        }
    }

    /**
     * Save data
     *
     * @param int|null $id
     */
    public function postSave(int $id = null)
    {
        unset($_POST['save'], $_POST['files']);

        if (!$id) {
            $location = url([ADMIN, 'members_sta', 'newMember']);
        } else {
            $location = url([ADMIN, 'members_sta', 'editMember', $id]);
        }

        // check if required fields are empty
        if (checkEmptyFields(['name', 'description', 'lang'], $_POST)) {
            $this->notify('failure', $this->lang('empty_inputs', 'general'));
            redirect($location, $_POST);
        }

        $_POST['name'] = trim($_POST['name']);
        if (!isset($_POST['markdown'])) {
            $_POST['markdown'] = 0;
        }

        if (
            $id == null && $this->db('members_sta')->where('name', '=', $_POST['name'])->where(
                'lang',
                $_POST['lang']
            )->oneArray()
        ) {
            $this->notify('failure', $this->lang('member_exists_name'));
            redirect(url([ADMIN, 'members_sta', 'newMember']), $_POST);
        } elseif (
            $id == null && $_POST['twitch_handle'] != null && $this->db('members_sta')->where(
                'twitch_handle',
                '=',
                $_POST['twitch_handle']
            )->where('lang', $_POST['lang'])->oneArray()
        ) {
            $this->notify('failure', $this->lang('member_exists_twitch'));
            redirect(url([ADMIN, 'members_sta', 'newMember']), $_POST);
        }

        if (($picture = isset_or($_FILES['picture']['tmp_name'], false)) || !$id) {
            $img = new \Inc\Core\Lib\Image();

            if (empty($picture) && !$id) {
                $picture = MODULES . '/members_sta/img/default.png';
            }
            if ($img->load($picture)) {
                if ($img->getInfos('width') < $img->getInfos('height')) {
                    $img->crop(0, 0, $img->getInfos('width'), $img->getInfos('width'));
                } else {
                    $img->crop(0, 0, $img->getInfos('height'), $img->getInfos('height'));
                }

                if ($img->getInfos('width') > 512) {
                    $img->resize(512, 512);
                }

                if ($id) {
                    $member = $this->db('members_sta')->oneArray($id);
                }

                $_POST['picture'] = uniqid('picture') . "." . $img->getInfos('type');
            }
        }

        if (!$id) { // new
            $query = $this->db('members_sta')->save($_POST);
            $location = url([ADMIN, 'members_sta', 'editMember', $this->db()->pdo()->lastInsertId()]);
        } else { // edit
            $query = $this->db('members_sta')->where('id', $id)->save($_POST);
        }

        if ($query) {
            if (isset($img) && $img->getInfos('width')) {
                if (isset($member)) {
                    unlink(UPLOADS . "/members_sta/" . $member['picture']);
                }

                $img->save(UPLOADS . "/members_sta/" . $_POST['picture']);
            }

            $this->notify('success', $this->lang('save_success'));
        } else {
            $this->notify('failure', $this->lang('save_failure'));
        }

        redirect($location);
    }

    /**
     * Remove member (with its picture)
     *
     * @param $id
     */
    public function getDeleteMember($id)
    {
        try {
            if ($post = $this->db('members_sta')->where('id', $id)->oneArray()) {
                unlink(UPLOADS . "/members_sta/" . $post['picture']);
            }
            $this->db('members_sta')->delete($id);

            $this->notify('success', $this->lang('delete_success'));
        } catch (Exception $e) {
            $this->notify('failure', $this->lang('delete_failure') . ' ' . $e->getMessage());
        }

        redirect(url([ADMIN, 'members_sta', 'manage']));
    }

    /**
     * remove picture
     */
    public function getDeletePicture($id)
    {
        if ($post = $this->db('members_sta')->where('id', $id)->oneArray()) {
            unlink(UPLOADS . "/members_sta/" . $post['picture']);
            $this->db('members_sta')->where('id', $id)->save(['picture' => null]);
            $this->notify('success', $this->lang('picture_deleted'));

            redirect(url([ADMIN, 'members_sta', 'edit', $id]));
        }
    }

    /**
     * image upload from WYSIWYG
     */
    public function postEditorUpload()
    {
        header('Content-type: application/json');
        $dir = UPLOADS . '/members_sta';
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
        echo $this->draw(MODULES . '/members_sta/js/admin/members_sta.js');
        exit();
    }

    /**
     * GET: /admin/members_sta/settings
     * Manage members_sta module general configuration
     *
     * @return string
     * @throws Exception
     */
    public function getSettings(): string
    {
        $value = $this->settings('members_sta');

        $assign['slug'] = $value['slug'];

        return $this->draw('settings.html', ['members_sta' => $assign]);
    }

    /**
     * Save settings.
     */
    public function postSaveSettings()
    {
        $update = [
            'slug' => $_POST['slug'],
        ];

        $errors = 0;
        foreach ($update as $field => $value) {
            if (!$this->db('settings')->where('module', 'members_sta')->where('field', $field)->save(['value' => $value])) {
                $errors++;
            }
        }

        if (!$errors) {
            $this->notify('success', $this->lang('save_settings_success'));
        } else {
            $this->notify('failure', $this->lang('save_settings_failure'));
        }

        redirect(url([ADMIN, 'members_sta', 'settings']));
    }

    /**
     * @throws Exception
     */
    protected function addHeaderFiles()
    {
        parent::addHeaderFiles();

        // ARE YOU SURE?
        $this->core->addJS(url('inc/jscripts/are-you-sure.min.js'));
        // MODULE SCRIPTS
        $this->core->addJS(url([ADMIN, 'members_sta', 'javascript']));
    }
}
