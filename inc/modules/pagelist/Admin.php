<?php

/**
    * This file is a third party module for Batflat ~ the lightweight, fast and easy CMS
    *
    * @author       Yohann Lorant <yohann.lorant@gmail.com>
    * @copyright    2017 Yohann Lorant
    * @license      MIT License
    * @link         http://nyan.at
    */

namespace Inc\Modules\Pagelist;

use Exception;
use Inc\Core\AdminModule;

class Admin extends AdminModule
{
    public array $assign = [];

    /**
     * Generates the admin menu
     */
    public function navigation(): array
    {
        return [
            $this->lang('manage', 'general')    => 'manage',
            $this->lang('add_new')              => 'newList',
            $this->lang('add_new_link')         => 'newLink'
        ];
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getManage(): string
    {
        // lang
        if (!empty($_GET['lang'])) {
            $lang = $_GET['lang'];
            $_SESSION['navigation']['last_lang'] = $lang;
        } elseif (!empty($_SESSION['navigation']['last_lang'])) {
            $lang = $_SESSION['navigation']['last_lang'];
        } else {
            $lang = $this->settings('settings', 'lang_site');
        }

        $this->assign['langs'] = $this->getLanguages($lang, 'active');

        // list
        $rows = $this->db('pagelist')->where('lang', $lang)->toArray();
        $linksList = $this->getListLinks($lang);

        $this->assign['list'] = [];
        if (count($rows)) {
            foreach ($rows as $row) {
                $row['editURL'] = url([ADMIN, 'pagelist', 'editList', $row['id']]);
                $row['delURL']  = url([ADMIN, 'pagelist', 'deleteList', $row['id']]);
                $row['viewURL'] = url(explode('_', $lang)[0] . '/' . $row['slug']);
                $row['description'] = str_limit($row['description'], 48);
                $row['links'] = [];

                if (isset($linksList[$row['id']])) {
                    foreach ($linksList[$row['id']] as $listLink) {
                        $listLink['upURL'] = url([ADMIN, 'pagelist', 'moveLink', 'up', $row['id'], $listLink['id']]);
                        $listLink['downURL'] = url([ADMIN, 'pagelist', 'moveLink', 'down', $row['id'], $listLink['id']]);
                        $listLink['editURL'] = url([ADMIN, 'pagelist', 'editLink', $row['id'], $listLink['id']]);
                        $listLink['delURL'] = url([ADMIN, 'pagelist', 'deleteLink', $row['id'], $listLink['id']]);
                        $listLink['viewURL'] = url(explode('_', $lang)[0] . '/' . $listLink['slug']);

                        $row['links'][] = $listLink;
                    }
                }


                $this->assign['list'][] = $row;
            }
        }

        return $this->draw('manage.html', ['list' => $this->assign]);
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getNewList(): string
    {
        $this->assign['editor'] = $this->settings('settings', 'editor');
        $this->assign['title'] = $this->lang('add_new');
        $defaultLang = $this->settings('settings.lang_site');
        $defaultTemplate = 'index.html';

        $this->addHeaderFiles();

        // Unsaved data with failure
        $e = getRedirectData();
        if (!empty($e)) {
            $this->assign['form'] = [
                'title' => isset_or($e['title'], ''),
                'description' => isset_or($e['description'], ''),
                'slug' => isset_or($e['url'], ''),
                'content' => isset_or($e['content'], ''),
                'markdown' => isset_or($e['markdown'], 0),
                'lang' => isset_or($e['lang'], $defaultLang)
            ];
        } else {
            $this->assign['form'] = [
                'title' => '',
                'description' => '',
                'slug' => '',
                'content' => '',
                'markdown' => 0,
                'lang' => $defaultLang
            ];
        }

        $this->assign['langs'] = $this->getLanguages($this->settings('settings.lang_site'), 'selected');
        $this->assign['templates'] = $this->getTemplates(isset_or($e['template'], $defaultTemplate));
        $this->assign['manageURL'] = url([ADMIN, 'pagelist', 'manage']);

        return $this->draw('form.list.html', ['list' => $this->assign]);
    }

    public function getEditList($id)
    {
        $this->assign['editor'] = $this->settings('settings', 'editor');
        $this->addHeaderFiles();

        $page = $this->db('pagelist')->where('id', $id)->oneArray();
        $defaultLang = $this->settings('settings.lang_site');

        if (!empty($page)) {
            $e = getRedirectData();
            // Unsaved data with failure
            if (!empty($e)) {
                $page = array_merge($page, [
                    'title' => isset_or($e['title'], ''),
                    'description' => isset_or($e['description'], ''),
                    'content' => isset_or($e['content'], ''),
                    'slug' => isset_or($e['slug'], ''),
                    'markdown' => isset_or($e['markdown'], 0),
                    'lang' => isset_or($e['lang'], $defaultLang)
                ]);
            }

            $this->assign['form'] = htmlspecialchars_array($page);
            $this->assign['form']['content'] =  $this->tpl->noParse($this->assign['form']['content']);

            $this->assign['title'] = $this->lang('edit_list');
            $this->assign['langs'] = $this->getLanguages($page['lang'], 'selected');
            $this->assign['templates'] = $this->getTemplates($page['template']);
            $this->assign['manageURL'] = url([ADMIN, 'pagelist', 'manage']);

            return $this->draw('form.list.html', ['list' => $this->assign]);
        } else {
            redirect(url([ADMIN, 'pagelist', 'manage']));
        }
    }

    public function getDeleteList($id)
    {
        if ($this->db('pagelist')->delete($id)) {
            $this->notify('success', $this->lang('delete_success'));
        } else {
            $this->notify('failure', $this->lang('delete_failure'));
        }

        redirect(url([ADMIN, 'pagelist', 'manage']));
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getNewLink(): string
    {
        $this->assign['title'] = $this->lang('add_new_link');

        // lang
        $lang = $_GET['lang'] ?? $this->settings('settings', 'lang_site');
        $this->assign['langs'] = $this->getLanguages($lang, 'selected');

        // Unsaved data with failure
        $e = getRedirectData();

        $this->assign['lists'] = $this->getLists($lang, isset_or($e['pagelist'], null));
        $this->assign['pages'] = $this->getPages($lang, isset_or($e['page'], null));
        $this->assign['picture'] = '';
        $this->assign['lockEdit'] = false;
        $this->assign['manageURL'] = url([ADMIN, 'pagelist', 'manage']);

        // MODULE SCRIPTS
        $this->core->addJS(url([ADMIN, 'pagelist', 'javascript', 'link']));

        return $this->draw('form.link.html', ['link' => $this->assign]);
    }

    /**
     * @param int|null $idPageList
     * @param int|null $idPage
     * @return string
     * @throws Exception
     */
    public function getEditLink(int $idPageList = null, int $idPage = null): string
    {
        $this->assign['title'] = $this->lang('edit_link');

        $link = $this->db('pagelist_pages')
                       ->where('pagelist', $idPageList)
                       ->where('page', $idPage)
                       ->oneArray();

        if (empty($link)) {
            redirect(url([ADMIN, 'pagelist', 'manage']));
        }

        // lang
        if (isset($_GET['lang'])) {
            $lang = $_GET['lang'];
        } else {
            $lang = $this->settings('settings', 'lang_site');
        }
        $this->assign['langs'] = $this->getLanguages($lang, 'selected');

        // Unsaved data with failure
        $e = getRedirectData();

        $this->assign['lists'] = $this->getLists($lang, isset_or($e['pagelist'], $link['pagelist']));
        $this->assign['pages'] = $this->getPages($lang, isset_or($e['page'], $link['page']));
        $this->assign['picture'] = $link['picture'];
        $this->assign['link'] = $link;
        $this->assign['lockEdit'] = true;
        $this->assign['manageURL'] = url([ADMIN, 'pagelist', 'manage']);

        // MODULE SCRIPTS
        $this->core->addJS(url([ADMIN, 'pagelist', 'javascript', 'link']));

        return $this->draw('form.link.html', ['link' => $this->assign]);
    }

    public function getMoveLink($direction, $idPageList, $idPage)
    {
        $links = $this->getLinksForList($idPageList);

        if ($links) {
            // Search for the link to move up/down
            $max = count($links) - 1;
            $askedLink = null;
            foreach ($links as $link) {
                if ($link['pagelist'] == $idPageList && $link['page'] == $idPage) {
                    $askedLink = $link;
                    break;
                }
            }

            if ($askedLink) {
                // Check the link position and move up/down
                $position = $askedLink['position'];
                if ($direction == 'up' && $position == 0 || $direction == 'down' && $position == $max) {
                    redirect(url([ADMIN, 'pagelist', 'manage']));
                }

                if ($direction == 'up') {
                    $position--;
                } else {
                    $position++;
                }

                // Get the link that previously occupied the spot and move it at the old place of the current link
                $oldPositionLink = null;
                foreach ($links as $link) {
                    if ($link['position'] == $position) {
                        $oldPositionLink = $link;
                        break;
                    }
                }

                // Save new position of requested link
                $result1 = $this->db('pagelist_pages')
                     ->where('pagelist', $idPageList)
                     ->where('page', $idPage)
                     ->save('position', $position);

                // Save position of adjacent link to the previous position of the modified link.
                if ($result1) {
                    $result2 = $this->db('pagelist_pages')
                         ->where('pagelist', $oldPositionLink['pagelist'])
                         ->where('page', $oldPositionLink['page'])
                         ->save('position', $askedLink['position']);
                }

                if ($result1 && $result2) {
                    $this->notify('success', $this->lang('move_link_success'));
                } else {
                    $this->notify('failure', $this->lang('move_link_failure'));
                }

                redirect(url([ADMIN, 'pagelist', 'manage']));
            }
        }

        $this->notify('failure', $this->lang('move_link_failure'));

        redirect(url([ADMIN, 'pagelist', 'manage']));
    }

    public function getDeleteLink($idPageList, $idPage)
    {
        $result = $this->db('pagelist_pages')
                       ->where('pagelist', $idPageList)
                       ->where('page', $idPage)
                       ->delete();

        if ($result) {
            $this->notify('success', $this->lang('delete_link_success'));
        } else {
            $this->notify('failure', $this->lang('delete_link_failure'));
        }

        redirect(url([ADMIN, 'pagelist', 'manage']));
    }

    /**
     * save list
     * @param int|null $id
     */
    public function postSaveList(int $id = null)
    {

        unset($_POST['save'], $_POST['files']);

        if (!$id) {
            $location = url([ADMIN, 'pagelist', 'addList']);
        } else {
            $location = url([ADMIN, 'pagelist', 'editList', $id]);
        }

        if (checkEmptyFields(['title', 'lang', 'template'], $_POST)) {
            $this->notify('failure', $this->lang('empty_inputs', 'general'));
            redirect($location, $_POST);
        }

        $_POST['title'] = trim($_POST['title']);
        $_POST['updated_at'] = date('Y-m-d H:i:s');

        if (!isset($_POST['markdown'])) {
            $_POST['markdown'] = 0;
        }

        if (empty($_POST['slug'])) {
            $_POST['slug'] = createSlug($_POST['title']);
        } else {
            $_POST['slug'] = createSlug($_POST['slug']);
        }

        if ($id != null && $this->db('pagelist')->where('slug', $_POST['slug'])->where('lang', $_POST['lang'])->where('id', '!=', $id)->oneArray()) {
            $this->notify('failure', $this->lang('page_exists'));
            redirect(url([ADMIN, 'pagelist', 'editList', $id]), $_POST);
        } elseif ($id == null && $this->db('pagelist')->where('slug', $_POST['slug'])->where('lang', $_POST['lang'])->oneArray()) {
            $this->notify('failure', $this->lang('page_exists'));
            redirect(url([ADMIN, 'pagelist', 'newList']), $_POST);
        }

        if (!$id) {
            $query = $this->db('pagelist')->save($_POST);
            $location = url([ADMIN, 'pagelist', 'editList', $this->db()->pdo()->lastInsertId()]);
        } else {
            $query = $this->db('pagelist')->where('id', $id)->save($_POST);
        }

        if ($query) {
            $this->notify('success', $this->lang('save_success'));
        } else {
            $this->notify('failure', $this->lang('save_failure'));
        }

        redirect($location);
    }

    /**
     * Save association between a pagelist and a page
     */
    public function postSaveLink()
    {
        unset($_POST['save']);

        $linkExists =  $this->db('pagelist_pages')
                            ->where('pagelist', '=', $_POST['pagelist'])
                            ->where('page', '=', $_POST['page'])
                            ->oneArray();

        if ($linkExists) {
            $location = url([ADMIN, 'pagelist', 'editLink', $_POST['pagelist'], $_POST['page']]);
        } else {
            $location = url([ADMIN, 'pagelist', 'newLink']);
        }

        if (checkEmptyFields(['pagelist', 'page'], $_POST)) {
            $this->notify('failure', $this->lang('empty_inputs', 'general'));
            redirect($location, $_POST);
        }

        if ($linkExists) {
            $this->db('pagelist_pages')
                 ->where('pagelist', $_POST['pagelist'])
                 ->where('page', $_POST['page'])
                 ->update('picture', $_POST['picture']);

            redirect(url([ADMIN, 'pagelist', 'manage']));
        } else {
            // Compute new link position
            $res = $this->db('pagelist_pages')
                        ->select(['total' => 'COUNT(1)'])
                        ->where('pagelist', $_POST['pagelist'])
                        ->oneArray();

            $_POST['position'] = $res['total'] ? $res['total'] : 0;

            $query = $this->db('pagelist_pages')->save($_POST);
        }

        redirect(url([ADMIN, 'pagelist', 'manage']));
    }

    /**
    * Handle image upload from the WYSIWYG
    */
    public function postEditorUpload()
    {
        header('Content-type: application/json');
        $dir = UPLOADS . '/pagelist';
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
     * Gets the sorted list of all links, by pagelist
     */
    protected function getListLinks($lang): array
    {
        $pages = $this->db('pagelist_pages')
                      ->select('pages.*')
                      ->select(['pl_id' => 'pagelist.id', 'plp_position' => 'pagelist_pages.position'])
                      ->join('pagelist', 'pagelist.id = pagelist_pages.pagelist')
                      ->join('pages', 'pagelist_pages.page = pages.id')
                      ->where('pagelist.lang', $lang)
                      ->asc('pagelist_pages.position')
                      ->toArray();

        $sortFunction = function ($a, $b) {
            return $a['plp_position'] - $b['plp_position'];
        };

            usort($pages, $sortFunction);

            $pagesByPagelist = [];
        foreach ($pages as $page) {
            if (!isset($pagesByPagelist[$page['pl_id']])) {
                $pagesByPagelist[$page['pl_id']] = [];
            }

            $pagesByPagelist[$page['pl_id']][] = $page;
        }

            return $pagesByPagelist;
    }

    protected function getLinksForList($listId): array
    {
        return $this->db('pagelist_pages')
                    ->where('pagelist', $listId)
                    ->asc('position')
                    ->toArray();
    }

    protected function getPages($lang, $selected = null): array
    {
        $rows = $this->db('pages')->where('lang', $lang)->toArray();
        $result = [];

        if (count($rows)) {
            foreach ($rows as $row) {
                if ($selected == $row['id']) {
                    $attr = 'selected';
                } else {
                    $attr = null;
                }

                $result[] = ['id' => $row['id'], 'title' => $row['title'], 'slug' => $row['slug'], 'attr' => $attr];
            }
        }

        return $result;
    }

    protected function getLists($lang, $selected = null): array
    {
        $rows =  $this->db('pagelist')->where('lang', $lang)->toArray();
        $result = [];

        if (count($rows)) {
            foreach ($rows as $row) {
                $attr = null;
                if ($selected == $row['id']) {
                    $attr = 'selected';
                }

                $result[] = ['id' => $row['id'], 'title' => $row['title'], 'slug' => $row['slug'], 'attr' => $attr];
            }
        }

        return $result;
    }

    /**
     * Gets the list of available templates in a theme
     *
     * @param string|null $selected
     * @return array
     * @throws Exception
     */
    private function getTemplates(string $selected = null): array
    {
        $theme = $this->settings('settings', 'theme');
        $tpls = glob(THEMES . '/' . $theme . '/*.html');

        $result = [];
        foreach ($tpls as $tpl) {
            if ($selected == basename($tpl)) {
                $attr = 'selected';
            } else {
                $attr = null;
            }

            $result[] = ['name' => basename($tpl), 'attr' => $attr];
        }
        return $result;
    }

    /**
    * module JavaScript
    */
    public function getJavascript($type)
    {
        header('Content-type: text/javascript');
        switch ($type) {
            case 'pagelist':
                echo $this->draw(MODULES . '/pagelist/js/admin/pagelist.js');
                break;
            case 'link':
                echo $this->draw(MODULES . '/pagelist/js/admin/pagelist_page.js');
                break;
        }

        exit();
    }

    protected function addHeaderFiles()
    {
        parent::addHeaderFiles();

        // MODULE SCRIPTS
        $this->core->addJS(url([ADMIN, 'pagelist', 'javascript', 'pagelist']));
    }
}
