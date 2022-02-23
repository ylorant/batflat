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

namespace Inc\Modules\Users;

use Inc\Core\AdminModule;
use Inc\Core\Lib\Image;

class Admin extends AdminModule
{
    public const STATUS_ACTIVE = 0;
    public const STATUS_INACTIVE = 1;
    public const STATUS_BLOCKED = 2;

    private array $assign = [];

    public function navigation(): array
    {
        return [
            $this->lang('manage', 'general') => 'manage',
            $this->lang('add_new') => 'add'
        ];
    }

    /**
    * users list
    */
    public function getManage(): string
    {
        $rows = $this->db('users')->asc('status')->toArray();
        $statuses = $this->getStatuses();

        foreach ($rows as &$row) {
            if (empty($row['fullname'])) {
                $row['fullname'] = '----';
            }
            $row['status'] = $statuses[$row['status']]['title'];
            $row['editURL'] = url([ADMIN, 'users', 'edit', $row['id']]);
            $row['delURL']  = url([ADMIN, 'users', 'delete', $row['id']]);
        }

        return $this->draw('manage.html', ['myId' => $this->core->getUserInfo('id'), 'users' => $rows]);
    }

    /**
     * add new user
     */
    public function getAdd(): string
    {
        if (!empty($redirectData = getRedirectData())) {
            $this->assign['form'] = filter_var_array($redirectData, FILTER_SANITIZE_STRING);
        } else {
            $this->assign['form'] = ['username' => '', 'email' => '', 'fullname' => '', 'description' => ''];
        }


        $this->assign['title'] = $this->lang('new_user');
        $this->assign['modules'] = $this->getModules('all');
        $this->assign['avatarURL'] = url(MODULES . '/users/img/default.jpg');

        return $this->draw('form.html', ['users' => $this->assign]);
    }

    /**
     * edit user
     *
     * @param $id
     * @return string|void
     */
    public function getEdit($id)
    {
        $user = $this->db('users')->oneArray($id);

        if (!empty($user)) {
            $this->assign['form'] = $user;
            $this->assign['title'] = $this->lang('edit_user');
            $this->assign['modules'] = $this->getModules($user['access']);
            $this->assign['statuses'] = $this->getStatuses();
            $this->assign['avatarURL'] = url(UPLOADS . '/users/' . $user['avatar']);

            return $this->draw('form.html', ['users' => $this->assign]);
        } else {
            redirect(url([ADMIN, 'users', 'manage']));
        }
    }

    /**
    * save user data
    */
    public function postSave($id = null)
    {
        $errors = 0;

        $formData = htmlspecialchars_array($_POST);

        // location to redirect
        $location = $id ? url([ADMIN, 'users', 'edit', $id]) : url([ADMIN, 'users', 'add']);

        // admin
        if ($id == 1) {
            $formData['access'] = ['all'];
        }

        // check if required fields are empty
        if (checkEmptyFields(['username', 'email', 'access'], $formData)) {
            $this->notify('failure', $this->lang('empty_inputs', 'general'));
            redirect($location, $formData);
        }

        // check if user already exists
        if ($this->userAlreadyExists($id)) {
            $errors++;
            $this->notify('failure', $this->lang('user_already_exists'));
        }
        // chech if e-mail adress is correct
        $formData['email'] = filter_var($formData['email'], FILTER_SANITIZE_EMAIL);
        if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors++;
            $this->notify('failure', $this->lang('wrong_email'));
        }
        // check if password is longer than 5 characters
        if (isset($formData['password']) && strlen($formData['password']) < 5) {
            $errors++;
            $this->notify('failure', $this->lang('too_short_pswd'));
        }
        // access to modules
        if ((count($formData['access']) == count($this->getModules())) || ($id == 1)) {
            $formData['access'] = 'all';
        } else {
            $formData['access'][] = 'dashboard';
            $formData['access'] = implode(',', $formData['access']);
        }

        // CREATE / EDIT
        if (!$errors) {
            unset($formData['save']);

            if (!empty($formData['password'])) {
                $formData['password'] = password_hash($formData['password'], PASSWORD_BCRYPT);
            }

            if (($photo = isset_or($_FILES['photo']['tmp_name'], false)) || !$id) {
                $img = new Image();

                if (empty($photo) && !$id) {
                    $photo = MODULES . '/users/img/default.jpg';
                }
                if ($img->load($photo)) {
                    if ($img->getInfos('width') < $img->getInfos('height')) {
                        $img->crop(0, 0, $img->getInfos('width'), $img->getInfos('width'));
                    } else {
                        $img->crop(0, 0, $img->getInfos('height'), $img->getInfos('height'));
                    }

                    if ($img->getInfos('width') > 512) {
                        $img->resize(512, 512);
                    }

                    if ($id) {
                        $user = $this->db('users')->oneArray($id);
                    }

                    $formData['avatar'] = uniqid('avatar') . "." . $img->getInfos('type');
                }
            }

            if (!$id) {    // new
                $query = $this->db('users')->save($formData);
            } else {        // edit
                $query = $this->db('users')->where('id', $id)->save($formData);
            }

            if ($query) {
                if (isset($img) && $img->getInfos('width')) {
                    if (isset($user)) {
                        unlink(UPLOADS . "/users/" . $user['avatar']);
                    }

                    $img->save(UPLOADS . "/users/" . $formData['avatar']);
                }

                $this->notify('success', $this->lang('save_success'));
            } else {
                $this->notify('failure', $this->lang('save_failure'));
            }

            redirect($location);
        }

        redirect($location, $formData);
    }

    /**
    * remove user
    */
    public function getDelete($id)
    {
        if ($id != 1 && $this->core->getUserInfo('id') != $id && ($user = $this->db('users')->oneArray($id))) {
            if ($this->db('users')->delete($id)) {
                if (!empty($user['avatar'])) {
                    unlink(UPLOADS . "/users/" . $user['avatar']);
                }

                $this->notify('success', $this->lang('delete_success'));
            } else {
                $this->notify('failure', $this->lang('delete_failure'));
            }
        }
        redirect(url([ADMIN, 'users', 'manage']));
    }

    /**
     * list of active modules
     * @param null $access
     * @return array
     */
    private function getModules($access = null): array
    {
        $result = [];
        $rows = $this->db('modules')->toArray();

        $accessArray = $access ? explode(',', $access) : [];

        foreach ($rows as $row) {
            if ($row['dir'] != 'dashboard') {
                $details = $this->core->getModuleInfo($row['dir']);

                if (empty($accessArray)) {
                    $attr = '';
                } else {
                    if (in_array($row['dir'], $accessArray) || ($accessArray[0] == 'all')) {
                        $attr = 'selected';
                    } else {
                        $attr = '';
                    }
                }
                $result[] = ['dir' => $row['dir'], 'name' => $details['name'], 'icon' => $details['icon'], 'icon-style' => $details['icon-style'], 'attr' => $attr];
            }
        }
        return $result;
    }

    /**
     * check if user already exists
     * @param int|null $id
     * @return bool
     */
    private function userAlreadyExists(int $id = null): bool
    {
        if (!$id) {    // new
            $count = $this->db('users')->where('username', $_POST['username'])->count();
        } else {        // edit
            $count = $this->db('users')->where('username', $_POST['username'])->where('id', '<>', $id)->count();
        }
        return $count > 0;
    }

    private function getStatuses(): array
    {
        return [
            ['value' => self::STATUS_ACTIVE, 'title' => $this->lang('active')],
            ['value' => self::STATUS_INACTIVE, 'title' => $this->lang('inactive')],
            ['value' => self::STATUS_BLOCKED, 'title' => $this->lang('blocked')]
        ];
    }
}
