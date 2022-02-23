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

namespace Inc\Modules\Galleries;

use Inc\Core\AdminModule;
use Inc\Core\Lib\Image;
use Inc\Core\Lib\Pagination;

class Admin extends AdminModule
{
    private array $thumbs = ['md' => 600, 'sm' => 300, 'xs' => 150];
    private string $uploads = UPLOADS . '/galleries';
    private array $assign = [];

    public function navigation(): array
    {
        return [
            $this->lang('manage', 'general') => 'manage',
        ];
    }

    /**
    * galleries manage
    */
    public function getManage(): string
    {
        // lang
        if (!empty($_GET['lang'])) {
            $lang = $_GET['lang'];
            $_SESSION['pages']['last_lang'] = $lang;
        } elseif (!empty($_SESSION['pages']['last_lang'])) {
            $lang = $_SESSION['pages']['last_lang'];
        } else {
            $lang = $this->settings('settings', 'lang_site');
        }

        // list
        $rows = $this->db('galleries')->toArray();
        if (count($rows)) {
            foreach ($rows as $row) {
                $row['tag']    = $this->tpl->noParse('{$gallery.' . $row['slug'] . '}');
                $row['viewURL'] = url(explode('_', $lang)[0] . '/' . $row['slug']);
                $row['editURL'] = url([ADMIN, 'galleries',  'edit', $row['id']]);
                $row['delURL']  = url([ADMIN, 'galleries', 'delete', $row['id']]);

                $this->assign[] = $row;
            }
        }

        return $this->draw('manage.html', ['galleries' => $this->assign]);
    }

    /**
    * add new gallery
    */
    public function anyAdd()
    {
        $location = [ADMIN, 'galleries', 'manage'];

        if (!empty($_POST['name'])) {
            $name = trim($_POST['name']);
            if (!$this->db('galleries')->where('slug', createSlug($name))->count()) {
                $query = $this->db('galleries')->save(['name' => $name, 'slug' => createSlug($name)]);

                if ($query) {
                    $id     = $this->db()->lastInsertId();
                    $dir    = $this->uploads . '/' . $id;

                    if (mkdir($dir, 0755, true)) {
                        $this->notify('success', $this->lang('add_gallery_success'));
                        $location = [ADMIN, 'galleries', 'edit', $this->db()->lastInsertId()];
                    }
                } else {
                    $this->notify('failure', $this->lang('add_gallery_failure'));
                }
            } else {
                $this->notify('failure', $this->lang('gallery_already_exists'));
            }
        } else {
            $this->notify('failure', $this->lang('empty_inputs', 'general'));
        }

        redirect(url($location));
    }

    /**
    * remove gallery
    */
    public function getDelete($id)
    {
        $query = $this->db('galleries')->delete($id);

        deleteDir($this->uploads . '/' . $id);

        if ($query) {
            $this->notify('success', $this->lang('delete_gallery_success'));
        } else {
            $this->notify('failure', $this->lang('delete_gallery_failure'));
        }

        redirect(url([ADMIN, 'galleries', 'manage']));
    }

    /**
    * edit gallery
    */
    public function getEdit($id, $page = 1): string
    {
        $this->assign['settings'] = $this->db('galleries')->oneArray($id);

        // pagination
        $totalRecords = $this->db('galleries_items')->where('gallery', $id)->toArray();
        $pagination = new Pagination(
            $page,
            count($totalRecords),
            10,
            url([ADMIN, 'galleries', 'edit', $id, '%d'])
        );
        $this->assign['pagination'] = $pagination->nav();
        $this->assign['page'] = $page;

        // items
        if ($this->assign['settings']['sort'] == 'ASC') {
            $rows = $this->db('galleries_items')->where('gallery', $id)
                    ->limit($pagination->offset() . ', ' . $pagination->getRecordsPerPage())
                    ->asc('id')->toArray();
        } else {
            $rows = $this->db('galleries_items')->where('gallery', $id)
                    ->limit($pagination->offset() . ', ' . $pagination->getRecordsPerPage())
                    ->desc('id')->toArray();
        }

        if (count($rows)) {
            foreach ($rows as $row) {
                $row['title'] = $this->tpl->noParse(htmlspecialchars($row['title']));
                $row['desc'] = $this->tpl->noParse(htmlspecialchars($row['desc']));
                $row['src'] = unserialize($row['src']);

                if (!isset($row['src']['sm'])) {
                    $row['src']['sm'] = $row['src']['xs'] ?? $row['src']['lg'];
                }

                $this->assign['images'][] = $row;
            }
        }

        $this->assign['id'] = $id;
        $this->assign['upload_limit'] = $this->getUploadLimit();

        $this->core->addCSS(url('https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css'));
        $this->core->addJS(url('https://cdn.jsdelivr.net/gh/mcstudios/glightbox/dist/js/glightbox.min.js'));
        $this->core->addJS(url('inc/jscripts/are-you-sure.min.js'));

        return $this->draw('edit.html', ['gallery' => $this->assign]);
    }

    /**
    * save gallery data
    */
    public function postSaveSettings($id)
    {
        $formData = htmlspecialchars_array($_POST);

        if (checkEmptyFields(['name', 'sort'], $formData)) {
            $this->notify('failure', $this->lang('empty_inputs', 'general'));
            redirect(url([ADMIN, 'galleries', 'edit', $id]));
        }

        $formData['slug'] = createSlug($formData['name']);
        if ($this->db('galleries')->where($id)->save($formData)) {
            $this->notify('success', $this->lang('save_settings_success'));
        }

        redirect(url([ADMIN, 'galleries', 'edit', $id]));
    }

    /**
    * save images data
    */
    public function postSaveImages($id, $page)
    {
        $query = false;

        foreach ($_POST['img'] as $key => $val) {
            $query = $this->db('galleries_items')->where($key)->save(['title' => $val['title'], 'desc' => $val['desc']]);
        }

        if ($query) {
            $this->notify('success', $this->lang('save_settings_success'));
        }

        redirect(url([ADMIN, 'galleries', 'edit', $id, $page]));
    }

    /**
    * image uploading
    */
    public function postUpload($id)
    {
        $dir    = $this->uploads . '/' . $id;
        $cntr   = 0;
        $query = false;

        if (!is_uploaded_file($_FILES['files']['tmp_name'][0])) {
            $this->notify('failure', $this->lang('no_files'));
        } else {
            foreach ($_FILES['files']['tmp_name'] as $image) {
                $img = new Image();

                if ($img->load($image)) {
                    $imgName = time() . $cntr++;
                    $imgPath = $dir . '/' . $imgName . '.' . $img->getInfos('type');
                    $src     = [];

                    // original size
                    $img->save($imgPath);
                    $src['lg'] = str_replace(BASE_DIR . '/', null, $imgPath);

                    // generate thumbs
                    foreach ($this->thumbs as $key => $width) {
                        if ($img->getInfos('width') > $width) {
                            $img->resize($width);
                            $img->save($thumbPath = "{$dir}/{$imgName}-{$key}.{$img->getInfos('type')}");
                            $src[$key] = str_replace(BASE_DIR . '/', null, $thumbPath);
                        }
                    }

                    $query = $this->db('galleries_items')->save(['src' => serialize($src), 'gallery' => $id]);
                } else {
                    $this->notify('failure', $this->lang('wrong_extension'), 'jpg, png, gif');
                }
            }

            if ($query) {
                $this->notify('success', $this->lang('add_images_success'));
            }
        }

        redirect(url([ADMIN, 'galleries', 'edit', $id]));
    }

    /**
    * remove image
    */
    public function getDeleteImage($id)
    {
        $image = $this->db('galleries_items')->where($id)->oneArray();
        if (!empty($image)) {
            if ($this->db('galleries_items')->delete($id)) {
                $images = unserialize($image['src']);
                foreach ($images as $src) {
                    if (file_exists(BASE_DIR . '/' . $src)) {
                        if (!unlink(BASE_DIR . '/' . $src)) {
                            $this->notify('failure', $this->lang('delete_image_failure'));
                        } else {
                            $this->notify('success', $this->lang('delete_image_success'));
                        }
                    }
                }
            }
        } else {
            $this->notify('failure', $this->lang('image_doesnt_exists'));
        }

        redirect(url([ADMIN, 'galleries', 'edit', $image['gallery']]));
    }

    /**
     * Return PHP server upload limit
     *
     * @return string
     */
    protected function getUploadLimit(): string
    {
        // Find the conf who limit the upload size
        $postMaxSize = $this->convertToBytes(ini_get('post_max_size'));
        $uploadMaxFilesize = $this->convertToBytes(ini_get('upload_max_filesize'));
        $maxUpload = max($postMaxSize, $uploadMaxFilesize);

        // Format the result
        $base = log($maxUpload, 1024);
        $suffixes = array('', 'K', 'M', 'G', 'T');
        $precision = 0;

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }

    /**
     * Return bytes from kilo, mega or gigabytes
     *
     * @param string $from
     * @return float|int
     */
    public function convertToBytes(string $from): ?int
    {
        $from = strtoupper($from);
        if (substr($from, -1) !== 'B') {
            $from .= 'B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $number = substr($from, 0, -2);
        $suffix = strtoupper(substr($from, -2));

        //B or no suffix
        if (is_numeric(substr($suffix, 0, 1))) {
            return preg_replace('/[^\d]/', '', $from);
        }

        $exponent = array_flip($units)[$suffix] ?? null;
        if ($exponent === null) {
            return null;
        }

        return $number * (1024 ** $exponent);
    }
}
