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

namespace Inc\Modules\Snippets;

use Exception;
use Inc\Core\AdminModule;

class Admin extends AdminModule
{
    public function navigation(): array
    {
        return [
            $this->lang('manage', 'general')    => 'manage',
            $this->lang('add')                  => 'add',
        ];
    }

    /**
    * list of snippets
    */
    public function getManage(): string
    {
        $rows = $this->db('snippets')->toArray();
        if (count($rows)) {
            foreach ($rows as &$row) {
                $row['tag'] = $this->tpl->noParse('{$snippet.' . $row['slug'] . '}');
                $row['editURL'] = url([ADMIN, 'snippets', 'edit', $row['id']]);
                $row['delURL'] = url([ADMIN, 'snippets', 'delete', $row['id']]);
            }
        }

        return $this->draw('manage.html', ['snippets' => $rows]);
    }

    /**
     * add new snippet
     * @throws Exception
     */
    public function getAdd(): string
    {
        return $this->getEdit();
    }

    /**
     * edit snippet
     * @throws Exception
     */
    public function getEdit($id = null): string
    {
        $this->addHeaderFiles();

        if (!empty($redirectData = getRedirectData())) {
            $assign = $redirectData;
        }

        if ($id === null) {
            $row = ['name' => isset_or($assign['name'], null), 'content' => isset_or($assign['content'], null)];

            $assign['title'] = $this->lang('add');
        } elseif (!empty($row = $this->db('snippets')->oneArray($id))) {
            $assign['title'] = $this->lang('edit');
        } else {
            redirect(url([ADMIN, 'snippets', 'manage']));
        }

        $assign = array_merge($assign, htmlspecialchars_array($row));
        $assign['languages'] = $this->getLanguages($this->settings('settings', 'lang_site'));

        $assign['content'] = [];
        preg_match_all("/{lang: ([a-z]{2}_[a-z]+)}(.*?){\/lang}/ms", $row['content'], $matches);
        foreach ($matches[1] as $key => $value) {
            $assign['content'][trim($value)] = $this->tpl->noParse(trim($matches[2][$key]));
        }

        $assign['editor'] = $this->settings('settings', 'editor');

        return $this->draw('form.html', ['snippets' => $assign]);
    }

    /**
    * remove snippet
    */
    public function getDelete($id)
    {
        if ($this->db('snippets')->delete($id)) {
            $this->notify('success', $this->lang('delete_success'));
        } else {
            $this->notify('failure', $this->lang('delete_failure'));
        }

        redirect(url([ADMIN, 'snippets', 'manage']));
    }

    /**
    * save snippet
    */
    public function postSave($id = null)
    {
        unset($_POST['save']);
        $formData = htmlspecialchars_array($_POST);

        if (checkEmptyFields(['name'], $formData)) {
            $this->notify('failure', $this->lang('empty_inputs', 'general'));

            if (!$id) {
                redirect(url([ADMIN, 'snippets', 'add']));
            } else {
                redirect(url([ADMIN, 'snippets', 'edit', $id]));
            }
        }

        $formData['name'] = trim($formData['name']);
        $formData['slug'] = createSlug($formData['name']);

        $tmp = null;
        foreach ($formData['content'] as $lang => $content) {
            $tmp .= "{lang: $lang}" . $content . "{/lang}";
        }

        $formData['content'] = $tmp;

        if ($id === null) { // new
            $location = url([ADMIN, 'snippets', 'add']);
            if (!$this->db('snippets')->where('slug', $formData['slug'])->count()) {
                if ($this->db('snippets')->save($formData)) {
                    $location = url([ADMIN, 'snippets', 'edit', $this->db()->lastInsertId()]);
                    $this->notify('success', $this->lang('save_success'));
                } else {
                    $this->notify('failure', $this->lang('save_failure'));
                }
            } else {
                $this->notify('failure', $this->lang('already_exists'));
            }
        } else {    // edit
            if (!$this->db('snippets')->where('slug', $formData['slug'])->where('id', '<>', $id)->count()) {
                if ($this->db('snippets')->where($id)->save($formData)) {
                    $this->notify('success', $this->lang('save_success'));
                } else {
                    $this->notify('failure', $this->lang('save_failure'));
                }
            } else {
                $this->notify('failure', $this->lang('already_exists'));
            }

            $location =  url([ADMIN, 'snippets', 'edit', $id]);
        }

        redirect($location, $formData);
    }

    /**
    * module JavaScript
    */
    public function getJavascript()
    {
        header('Content-type: text/javascript');
        echo $this->draw(MODULES . '/snippets/js/admin/snippets.js');
        exit();
    }

    protected function addHeaderFiles()
    {
        parent::addHeaderFiles();

        // MODULE SCRIPTS
        $this->core->addJS(url([ADMIN, 'snippets', 'javascript']));
    }
}
