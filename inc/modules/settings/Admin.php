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

namespace Inc\Modules\Settings;

use DateTimeZone;
use Exception;
use Inc\Core\AdminModule;
use Inc\Core\Lib\License;
use Inc\Core\Lib\HttpRequest;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;
use Inc\Modules\Settings\Inc\RecursiveDotFilterIterator;

class Admin extends AdminModule
{
    private array $assign = [];
    private string $feed_url = "https://feed.sruu.pl";

    public function init()
    {
        if (file_exists(BASE_DIR . '/inc/engine')) {
            deleteDir(BASE_DIR . '/inc/engine');
        }
    }

    public function navigation(): array
    {
        return [
            $this->lang('general')          => 'general',
            $this->lang('theme', 'general') => 'theme',
            $this->lang('translation')      => 'translation',
            $this->lang('updates')          => 'updates',
        ];
    }

    public function getGeneral(): string
    {
        $settings = $this->settings('settings');

        // lang
        if (isset($_GET['lang']) && !empty($_GET['lang'])) {
            $lang = $_GET['lang'];
        } else {
            $lang = $settings['lang_site'];
        }

        $settings['langs'] = [
            'site' => $this->getLanguages($settings['lang_site'], 'selected'),
            'admin' => $this->getLanguages($settings['lang_admin'], 'selected')
        ];

        $settings['faviconDeleteURL'] = url([ADMIN, 'settings', 'deleteFavicon']);

        $settings['themes'] = $this->getThemes();
        $settings['pages'] = $this->getPages($lang);
        $settings['timezones'] = $this->getTimezones();
        $settings['system'] = [
            'php'           => PHP_VERSION,
            'sqlite'        => $this->db()->pdo()->query('select sqlite_version()')->fetch()[0],
            'sqlite_size'   => $this->roundSize(filesize(BASE_DIR . '/inc/data/database.sdb')),
            'system_size'   => $this->roundSize($this->directorySize(BASE_DIR)),
        ];

        $settings['license'] = [];
        $settings['license']['type'] = $this->verifyLicense();
        switch ($settings['license']['type']) {
            case License::FREE:
                $settings['license']['name'] = $this->lang('free');
                break;
            case License::COMMERCIAL:
                $settings['license']['name'] = $this->lang('commercial');
                break;
            default:
                $settings['license']['name'] = $this->lang('invalid_license');
        }

        foreach ($this->core->getRegisteredPages() as $page) {
            $settings['pages'][] = $page;
        }

        if (!empty($redirectData = getRedirectData())) {
            $settings = array_merge($settings, $redirectData);
        }

        $this->tpl->set('settings', $this->tpl->noParse_array(htmlspecialchars_array($settings)));
        $this->tpl->set('updateurl', url([ADMIN, 'settings', 'updates']));

        return $this->draw('general.html');
    }

    public function postSaveGeneral()
    {
        unset($_POST['save']);
        $arrayKeys = array_keys($_POST);
        if (checkEmptyFields(array_diff($arrayKeys, ['favicon']), $_POST)) {
            $this->notify('failure', $this->lang('empty_inputs', 'general'));
            redirect(url([ADMIN, 'settings', 'general']), $_POST);
        } else {
            $errors = 0;

            if ($this->settings('settings', 'autodetectlang')) {
                $_POST['autodetectlang'] = isset_or($_POST['autodetectlang'], 0);
            }

            $faviconTmpPath = null;
            $newFaviconName = null;
            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $faviconTmpPath = $_FILES['favicon']['tmp_name'];
                $faviconName = $_FILES['favicon']['name'];
                $faviconSize = $_FILES['favicon']['size'];
                $faviconCmps = explode(".", $faviconName);
                $faviconExtension = strtolower(end($faviconCmps));

                if ($faviconExtension == 'ico' && $faviconSize > 0) {
                    $newFaviconName = md5(time() . $faviconName) . '.' . $faviconExtension;
                    $_POST['favicon'] = $newFaviconName;
                } else {
                    $_POST['favicon'] = '';
                    $errors++;
                }
            }

            foreach ($_POST as $field => $value) {
                if (!$this->db('settings')->where('module', 'settings')->where('field', $field)->save(['value' => $value])) {
                    $errors++;
                }
            }

            if (!$errors) {
                if (!file_exists(UPLOADS . "/settings")) {
                    mkdir(UPLOADS . "/settings", 0777, true);
                }
                if (!empty($faviconTmpPath) && !empty($newFaviconName)) {
                    move_uploaded_file($faviconTmpPath, UPLOADS . "/settings/" . $newFaviconName);
                }

                $this->notify('success', $this->lang('save_settings_success'));
            } else {
                $this->notify('failure', $this->lang('save_settings_failure'));
            }

            unset($_SESSION['lang']);
            redirect(url([ADMIN, 'settings', 'general']));
        }
    }

    /**
     * remove post cover
     */
    public function getDeleteFavicon()
    {
        if ($post = $this->db('settings')->where('module', 'settings')->where('field', 'favicon')->oneArray()) {
            unlink(UPLOADS . "/settings/" . $post['value']);
            $this->db('settings')->where('module', 'settings')->where('field', 'favicon')->save(['value' => '']);
            $this->notify('success', $this->lang('favicon_deleted'));
            redirect(url([ADMIN, 'settings', 'general']));
        }
    }

    public function anyLicense()
    {
        if (isset($_POST['license-key'])) {
            $licenseKey = str_replace('-', '', $_POST['license-key']);

            if (!($licenseKey = License::getLicenseData($licenseKey))) {
                $this->notify('failure', $this->lang('license_invalid_key'));
            }

            $verify = License::verify($licenseKey);
            if ($verify != License::COMMERCIAL) {
                $this->notify('failure', $this->lang('license_invalid_key'));
            } else {
                $this->notify('success', $this->lang('license_good_key'));
            }
        } elseif (isset($_GET['downgrade'])) {
            $this->db('settings')->where('module', 'settings')->where('field', 'license')->save(['value' => '']);
        }

        redirect(url([ADMIN,'settings','general']));
    }

    /**
     * @throws Exception
     */
    public function anyTheme($theme = null, $file = null): string
    {
        $this->addHeaderFiles();

        if (empty($theme) && empty($file)) {
            $this->tpl->set('settings', $this->settings('settings'));
            $this->tpl->set('themes', $this->getThemes());
            return $this->draw('themes.html');
        } else {
            if ($file == 'activate') {
                $this->db('settings')->where('module', 'settings')->where('field', 'theme')->save(['value' => $theme]);
                $this->notify('success', $this->lang('theme_changed'));
                redirect(url([ADMIN, 'settings', 'theme']));
            }

            $this->assign['files'] = $this->getThemeFiles($file, $theme);

            if ($file) {
                $file = $this->assign['files'][$file]['path'];
            } else {
                $file = reset($this->assign['files'])['path'];
            }

            $this->assign['content'] = $this->tpl->noParse(htmlspecialchars(file_get_contents($file)));
            $this->assign['lang']    = pathinfo($file, PATHINFO_EXTENSION);

            if (isset($_POST['save']) && !FILE_LOCK) {
                if (file_put_contents($file, htmlspecialchars_decode($_POST['content']))) {
                    $this->notify('success', $this->lang('save_file_success'));
                } else {
                    $this->notify('failure', $this->lang('save_file_failure'));
                }

                redirect(url([ADMIN, 'settings', 'theme', $theme, md5($file)]));
            }

            $this->tpl->set('settings', $this->settings('settings'));
            $this->tpl->set('theme', array_merge($this->getThemes($theme), $this->assign));
            return $this->draw('theme.html');
        }
    }

    public function getTranslation()
    {
        if (isset($_GET['export'])) {
            $export = $_GET['export'];
            if (file_exists(BASE_DIR . '/inc/lang/' . $export)) {
                $file = tempnam("tmp", "zip");
                $zip = new ZipArchive();
                $zip->open($file, ZipArchive::OVERWRITE);

                foreach (glob(BASE_DIR . '/inc/lang/' . $export . '/admin/*.ini') as $f) {
                    $zip->addFile($f, str_replace(BASE_DIR, '', $f));
                }

                foreach (glob(MODULES . '/*/lang/' . $export . '.ini') as $f) {
                    $zip->addFile($f, str_replace(BASE_DIR, '', $f));
                }

                foreach (glob(MODULES . '/*/lang/admin/' . $export . '.ini') as $f) {
                    $zip->addFile($f, str_replace(BASE_DIR, '', $f));
                }

                // Close and send to users
                $zip->close();
                header('Content-Type: application/zip');
                header('Content-Length: ' . filesize($file));
                header('Content-Disposition: attachment; filename="Batflat_' . str_replace('.', '-', $this->settings('settings', 'version')) . '_' . $export . '.zip"');
                readfile($file);
                unlink($file);
                exit();
            }
        }

        if (!isset($_GET['lang'])) {
            $_GET['lang'] = $this->settings('settings', 'lang_site');
        }

        if (!isset($_GET['source'])) {
            $_GET['source'] = 0;
        }

        $settings = [
            'langs'         => $this->getLanguages($_GET['lang']),
            'langs_all'     => $this->getLanguages($_GET['lang'], 'active', true),
            'selected'      => $_GET['lang'],
        ];

        $translations = $this->getAllTranslations($_GET['lang']);
        $translation = $translations[$_GET['source']];
        $translations = array_keys($translations);

        $this->tpl->set('translation', $translation);
        $this->tpl->set('translations', $translations);
        $this->tpl->set('module', $_GET['source']);
        $this->tpl->set('settings', $settings);

        return $this->draw('translation.html');
    }

    public function postTranslation()
    {
        if (!isset($_GET['lang'])) {
            $_GET['lang'] = $this->settings('settings', 'lang_site');
        }

        if (!isset($_GET['source'])) {
            $_GET['source'] = 0;
        }

        if (isset($_POST['upload']) && FILE_LOCK === false) {
            $zip = new ZipArchive();
            $allowedDest = '/(.*?inc\/)((jscripts|lang|modules).*$)/';
            $count = 0;
            $file = !empty($_FILES['lang_package']['tmp_name']) ? $_FILES['lang_package']['tmp_name'] : '/';
            $open = $zip->open($file);
            if ($open === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = pathinfo($zip->getNameIndex($i));
                    if (
                        isset($filename['extension'])
                            && ($filename['extension'] == 'ini' || $filename['extension'] == 'js')
                    ) {
                        preg_match($allowedDest, $filename['dirname'], $matches);
                        $dest = realpath(BASE_DIR) . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . $matches[2];
                        if (!file_exists($dest)) {
                            mkdir($dest, 0755, true);
                        }

                        copy(
                            'zip://' . $file . '#' . $filename['dirname']
                                . DIRECTORY_SEPARATOR . $filename['basename'],
                            $dest . DIRECTORY_SEPARATOR . $filename['basename']
                        );
                        $count++;
                    }
                }

                if ($count > 0) {
                    $this->notify('success', $this->lang('lang_import_success'));
                } else {
                    $this->notify('failure', $this->lang('lang_import_error'));
                }

                $zip->close();
            }
        }

        if (isset($_POST['new_language']) && FILE_LOCK === false) {
            $lang = $_POST['language_name'];
            if (preg_match("/^[a-z]{2}_[a-z]+$/", $lang)) {
                if (file_exists(BASE_DIR . '/inc/lang/' . $lang)) {
                    $this->notify('failure', $this->lang('new_lang_exists'));
                } else {
                    if (mkdir(BASE_DIR . '/inc/lang/' . $lang . '/admin', 0755, true)) {
                        $this->notify('success', $this->lang('new_lang_success'));
                        redirect(url([ADMIN, 'settings', 'translation?lang=' . $lang]));
                    } else {
                        $this->notify('success', $this->lang('new_lang_create_fail'));
                    }
                }
            } else {
                $this->notify('failure', $this->lang('new_lang_failure'));
            }
        }
        if (isset($_POST['save'], $_POST[$_GET['source']]) && FILE_LOCK === false) {
            $toSave = $_POST[$_GET['source']];
            if (is_numeric($_GET['source'])) {
                $pad = 0;
                array_walk($toSave['admin'], function ($value, $key) use (&$pad) {
                    $length = strlen($key);
                    if ($pad < $length) {
                        $pad = $length;
                    }
                });

                $pad = $pad + 4 - $pad % 4;

                $output = [];
                foreach ($toSave['admin'] as $key => $value) {
                    $value = preg_replace("/(?<!\\\\)\"/", '\"', $value);
                    $output[] = str_pad($key, $pad) . '= "' . $value . '"';
                }

                $output = implode("\n", $output);

                if (file_put_contents('../inc/lang/' . $_GET['lang'] . '/admin/general.ini', $output)) {
                    $this->notify('success', $this->lang('save_file_success'));
                } else {
                    $this->notify('failure', $this->lang('save_file_failure'));
                }
            } else {
                if (isset($toSave['front'])) {
                    $pad = 0;
                    array_walk($toSave['front'], function ($value, $key) use (&$pad) {
                        $length = strlen($key);
                        if ($pad < $length) {
                            $pad = $length;
                        }
                    });

                    $pad = $pad + 4 - $pad % 4;

                    $output = [];
                    foreach ($toSave['front'] as $key => $value) {
                        $value = preg_replace("/(?<!\\\\)\"/", '\"', $value);
                        $output[] = str_pad($key, $pad) . '= "' . $value . '"';
                    }

                    $output = implode("\n", $output);

                    if (file_put_contents(MODULES . '/' . $_GET['source'] . '/lang/' . $_GET['lang'] . '.ini', $output)) {
                        $this->notify('success', $this->lang('save_file_success'));
                    } else {
                        $this->notify('failure', $this->lang('save_file_failure'));
                    }
                }

                if (isset($toSave['admin'])) {
                    $pad = 0;
                    array_walk($toSave['admin'], function ($value, $key) use (&$pad) {
                        $length = strlen($key);
                        if ($pad < $length) {
                            $pad = $length;
                        }
                    });

                    $pad = $pad + 4 - $pad % 4;

                    $output = [];
                    foreach ($toSave['admin'] as $key => $value) {
                        $value = preg_replace("/(?<!\\\\)\"/", '\"', $value);
                        $output[] = str_pad($key, $pad) . '= "' . $value . '"';
                    }

                    $output = implode("\n", $output);

                    if (file_put_contents(MODULES . '/' . $_GET['source'] . '/lang/admin/' . $_GET['lang'] . '.ini', $output)) {
                        $this->notify('success', $this->lang('save_file_success'));
                    } else {
                        $this->notify('failure', $this->lang('save_file_failure'));
                    }
                }
            }
        }

        redirect(url([ADMIN, 'settings', 'translation?lang=' . $_GET['lang']]));
    }

    /**
    * remove language from server
    */
    public function getDeleteLanguage($name)
    {
        if (($this->settings('settings', 'lang_site') == $name) || ($this->settings('settings', 'lang_admin') == $name)) {
            $this->notify('failure', $this->lang('lang_delete_failure'));
        } else {
            if (unlink(BASE_DIR . '/inc/lang/' . $name . '/.lock') && deleteDir(BASE_DIR . '/inc/lang/' . $name)) {
                $this->notify('success', $this->lang('lang_delete_success'));
            } else {
                $this->notify('failure', $this->lang('lang_delete_failure'));
            }
        }

        redirect(url([ADMIN, 'settings', 'translation']));
    }

    /**
    * activate language
    */
    public function getActivateLanguage($name)
    {
        if (unlink(BASE_DIR . '/inc/lang/' . $name . '/.lock')) {
            $this->notify('success', $this->lang('lang_activate_success'));
        } else {
            $this->notify('failure', $this->lang('lang_activate_failure'));
        }

        redirect(url([ADMIN, 'settings', 'translation']));
    }

    /**
    * deactivate language
    */
    public function getDeactivateLanguage($name)
    {
        if (($this->settings('settings', 'lang_site') == $name) || ($this->settings('settings', 'lang_admin') == $name)) {
            $this->notify('failure', $this->lang('lang_deactivate_failure'));
        } else {
            if (touch(BASE_DIR . '/inc/lang/' . $name . '/.lock')) {
                $this->notify('success', $this->lang('lang_deactivate_success'));
            } else {
                $this->notify('failure', $this->lang('lang_deactivate_failure'));
            }
        }

        redirect(url([ADMIN, 'settings', 'translation']));
    }

    public function anyUpdates(): string
    {
        $this->tpl->set('allow_curl', intval(function_exists('curl_init')));
        $settings = $this->settings('settings');

        if (isset($_POST['check'])) {
            $request = $this->updateRequest('/batflat/update', [
                'ip' => isset_or($_SERVER['SERVER_ADDR'], $_SERVER['SERVER_NAME']),
                'version' => $settings['version'],
                'domain' => url(),
            ]);

            $this->updateSettings('update_check', time());

            if (!is_array($request)) {
                $this->tpl->set('error', $request);
            } elseif ($request['status'] == 'error') {
                $this->tpl->set('error', $request['message']);
            } else {
                $this->updateSettings('update_version', $request['data']['version']);
                $this->updateSettings('update_changelog', $request['data']['changelog']);
                $this->tpl->set('update_version', $request['data']['version']);

                // if(DEV_MODE)
                //     $this->tpl->set('request', $request);
            }
        } elseif (isset($_POST['update'])) {
            if (!class_exists("ZipArchive")) {
                $this->tpl->set('error', "ZipArchive is required to update Batflat.");
            }

            if (!isset($_GET['manual'])) {
                $request = $this->updateRequest('/batflat/update', [
                    'ip' => isset_or($_SERVER['SERVER_ADDR'], $_SERVER['SERVER_NAME']),
                    'version' => $settings['version'],
                    'domain' => url(),
                ]);

                $this->download($request['data']['download'], BASE_DIR . '/tmp/latest.zip');
            } else {
                $package = glob(BASE_DIR . '/batflat-*.zip');
                if (!empty($package)) {
                    $package = array_shift($package);
                    $this->rcopy($package, BASE_DIR . '/tmp/latest.zip');
                }
            }

            define("UPGRADABLE", true);
            // Making backup
            $backup_date = date('YmdHis');
            $this->rcopy(BASE_DIR, BASE_DIR . '/backup/' . $backup_date . '/', 0755, [BASE_DIR . '/backup', BASE_DIR . '/tmp/latest.zip', (isset($package) ? BASE_DIR . '/' . basename($package) : '')]);

            // Unzip latest update
            $zip = new ZipArchive();
            $zip->open(BASE_DIR . '/tmp/latest.zip');
            $zip->extractTo(BASE_DIR . '/tmp/update');

            // Copy files
            $this->rcopy(BASE_DIR . '/tmp/update/inc/css', BASE_DIR . '/inc/css');
            $this->rcopy(BASE_DIR . '/tmp/update/inc/core', BASE_DIR . '/inc/core');
            $this->rcopy(BASE_DIR . '/tmp/update/inc/jscripts', BASE_DIR . '/inc/jscripts');
            $this->rcopy(BASE_DIR . '/tmp/update/inc/lang', BASE_DIR . '/inc/lang');
            $this->rcopy(BASE_DIR . '/tmp/update/inc/modules', BASE_DIR . '/inc/modules');

            // Restore defines
            $this->rcopy(BASE_DIR . '/backup/' . $backup_date . '/inc/core/defines.php', BASE_DIR . '/inc/core/defines.php');

            // Run upgrade script
            $version = $settings['version'];
            $new_version = include(BASE_DIR . '/tmp/update/upgrade.php');

            // Close archive and delete all unnecessary files
            $zip->close();
            unlink(BASE_DIR . '/tmp/latest.zip');
            deleteDir(BASE_DIR . '/tmp/update');

            $this->updateSettings('version', $new_version);
            $this->updateSettings('update_version', 0);
            $this->updateSettings('update_changelog', '');
            $this->updateSettings('update_check', time());

            sleep(2);
            redirect(url([ADMIN, 'settings', 'updates']));
        } elseif (isset($_GET['reset'])) {
            $this->updateSettings('update_version', 0);
            $this->updateSettings('update_changelog', '');
            $this->updateSettings('update_check', 0);
        } elseif (isset($_GET['manual'])) {
            $package = glob(BASE_DIR . '/batflat-*.zip');
            $version = false;
            if (!empty($package)) {
                $package_path = array_shift($package);
                preg_match('/batflat\-([0-9\.a-z]+)\.zip$/', $package_path, $matches);
                $version = $matches[1];
            }

            $manual_mode = ['version' => $version];
        }

        $this->settings->reload();
        $settings = $this->settings('settings');
        $this->tpl->set('settings', $settings);
        $this->tpl->set('manual_mode', isset_or($manual_mode, false));
        return $this->draw('update.html');
    }

    public function postChangeOrderOfNavItem()
    {
        foreach ($_POST as $module => $order) {
            $this->db('modules')->where('dir', $module)->save(['sequence' => $order]);
        }
        exit();
    }

    /**
     * @throws Exception
     */
    public function checkUpdate(): bool
    {
        $settings = $this->settings('settings');
        if (time() - $settings['update_check'] > 3600 * 6) {
            $request = $this->updateRequest('/batflat/update', [
                'ip' => isset_or($_SERVER['SERVER_ADDR'], $_SERVER['SERVER_NAME']),
                'version' => $settings['version'],
                'domain' => url(),
            ]);

            if (is_array($request) && $request['status'] != 'error') {
                $settings['update_version'] = $request['data']['version'];
                $this->updateSettings('update_version', $request['data']['version']);
                $this->updateSettings('update_changelog', $request['data']['changelog']);
            }

            $this->updateSettings('update_check', time());
        }

        if (cmpver($settings['update_version'], $settings['version']) === 1) {
            return true;
        }

        return false;
    }

    /**
     * module JavaScript
     */
    public function getJavascript()
    {
        header('Content-type: text/javascript');
        echo $this->draw(MODULES . '/settings/js/admin/settings.js');
        exit();
    }

    protected function addHeaderFiles()
    {
        parent::addHeaderFiles();

        // MODULE SCRIPTS
        $this->core->addJS(url([ADMIN, 'settings', 'javascript']));
        // MODULE CSS
        $this->core->addCSS(url(MODULES . '/settings/css/admin/settings.css'));
    }

    private function updateRequest($resource, $params = [])
    {
        $output = HttpRequest::post($this->feed_url . $resource, $params);
        if (!$output) {
            $output = HttpRequest::getStatus();
        } else {
            $output = json_decode($output, true);
        }

        return $output;
    }

    private function download($source, $dest)
    {
        set_time_limit(0);
        $fp = fopen($dest, 'w+');
        $ch = curl_init($source);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }

    /**
     * list of themes
     * @param null $theme
     * @return array
     */
    private function getThemes($theme = null): array
    {
        $themes = glob(THEMES . '/*', GLOB_ONLYDIR);
        $return = [];
        foreach ($themes as $e) {
            if ($e != THEMES . '/admin') {
                $manifest = array_fill_keys(['name', 'version', 'author', 'email', 'thumb'], 'Unknown');
                $manifest['name'] = basename($e);
                $manifest['thumb'] = '../admin/img/unknown_theme.png';

                if (file_exists($e . '/manifest.json')) {
                    $manifest = array_merge($manifest, json_decode(file_get_contents($e . '/manifest.json'), true));
                }

                if ($theme == basename($e)) {
                    return array_merge($manifest, ['dir' => basename($e)]);
                }

                $return[] = array_merge($manifest, ['dir' => basename($e)]);
            }
        }

        return $return;
    }

    /**
     * list of pages
     * @param string $lang
     * @return array
     */
    private function getPages(string $lang): array
    {
        $result = [];
        $rows = $this->db('pages')->where('lang', $lang)->toArray();
        if (count($rows)) {
            foreach ($rows as $row) {
                $result[] = ['id' => $row['id'], 'title' => $row['title'], 'slug' => $row['slug']];
            }
        }
        return $result;
    }

    /**
     * list of theme files (html, css & js)
     * @param null $selected
     * @param null $theme
     * @return array
     * @throws Exception
     */
    private function getThemeFiles($selected = null, $theme = null): array
    {
        $theme = ($theme ? $theme : $this->settings('settings', 'theme'));
        $files = $this->rglob(THEMES . '/' . $theme . '/*.html');
        $files = array_merge($files, $this->rglob(THEMES . '/' . $theme . '/*.css'));
        $files = array_merge($files, $this->rglob(THEMES . '/' . $theme . '/*.js'));

        $result = [];
        foreach ($files as $file) {
            if ($selected && ($selected == md5($file))) {
                $attr = 'selected';
            } else {
                $attr = null;
            }

            $result[md5($file)] = ['name' => basename($file), 'path' => $file, 'short' => str_replace(BASE_DIR, '', $file), 'attr' => $attr];
        }

        return $result;
    }

    private function updateSettings($field, $value)
    {
        return $this->settings('settings', $field, $value);
    }

    private function rcopy($source, $dest, $permissions = 0755, $expect = [])
    {
        foreach ($expect as $e) {
            if ($e == $source) {
                return;
            }
        }

        if (is_link($source)) {
            return symlink(readlink($source), $dest);
        }

        if (is_file($source)) {
            if (!is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0777, true);
            }

            return copy($source, $dest);
        }

        if (!is_dir($dest)) {
            mkdir($dest, $permissions, true);
        }

        $dir = dir($source);
        while (false !== $entry = $dir->read()) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            $this->rcopy("$source/$entry", "$dest/$entry", $permissions, $expect);
        }

        $dir->close();
        return true;
    }

    private function verifyLicense(): int
    {
        $licenseArray = (array) json_decode(base64_decode($this->settings('settings', 'license')), true);
        $license = array_replace(array_fill(0, 5, null), $licenseArray);
        list($md5hash, $pid, $lcode, $dcode, $tstamp) = $license;

        if (empty($md5hash)) {
            return License::FREE;
        }

        if ($md5hash == md5($pid . $lcode . $dcode . domain(false))) {
            return License::COMMERCIAL;
        }

        return License::ERROR;
    }

    private function getTimezones(): array
    {
        $regions = array(
            DateTimeZone::AFRICA,
            DateTimeZone::AMERICA,
            DateTimeZone::ANTARCTICA,
            DateTimeZone::ASIA,
            DateTimeZone::ATLANTIC,
            DateTimeZone::AUSTRALIA,
            DateTimeZone::EUROPE,
            DateTimeZone::INDIAN,
            DateTimeZone::PACIFIC,
            DateTimeZone::UTC,
        );

        $timezones = array();
        foreach ($regions as $region) {
            $timezones = array_merge($timezones, DateTimeZone::listIdentifiers($region));
        }

        $timezone_offsets = array();
        foreach ($timezones as $timezone) {
            $tz = new DateTimeZone($timezone);
            $timezone_offsets[$timezone] = $tz->getOffset(new \DateTime());
        }

        // sort timezone by offset
        asort($timezone_offsets);

        $timezone_list = array();
        foreach ($timezone_offsets as $timezone => $offset) {
            $offset_prefix = $offset < 0 ? '-' : '+';
            $offset_formatted = gmdate('H:i', abs($offset));

            $pretty_offset = "UTC${offset_prefix}${offset_formatted}";

            $timezone_list[$timezone] = "(${pretty_offset}) $timezone";
        }

        return $timezone_list;
    }

    private function getAllTranslations($lang): array
    {
        $modules = [];

        $general = parse_ini_file('../inc/lang/en_english/admin/general.ini');

        if (file_exists('../inc/lang/' . $lang . '/admin/general.ini')) {
            $current = parse_ini_file('../inc/lang/' . $lang . '/admin/general.ini');
        } else {
            $current = [];
        }

        foreach ($general as $key => $value) {
            $modules[0]['admin'][] = [
                'key'       => $key,
                'value'     => isset_or($current[$key], null),
                'english'   => $value
            ];
        }

        $dirs = glob(MODULES . '/*');
        foreach ($dirs as $dir) {
            $modules[basename($dir)] = [];
            if (file_exists($dir . '/lang/en_english.ini')) {
                $tmp = parse_ini_file($dir . '/lang/en_english.ini');

                if (file_exists($dir . '/lang/' . $lang . '.ini')) {
                    $current = parse_ini_file($dir . '/lang/' . $lang . '.ini');
                } else {
                    $current = [];
                }

                foreach ($tmp as $key => $value) {
                    $modules[basename($dir)]['front'][] = [
                        'key'       => $key,
                        'value'     => isset_or($current[$key], null),
                        'english'   => $value
                    ];
                }
            }

            if (file_exists($dir . '/lang/admin/en_english.ini')) {
                $tmp = parse_ini_file($dir . '/lang/admin/en_english.ini');

                if (file_exists($dir . '/lang/admin/' . $lang . '.ini')) {
                    $current = parse_ini_file($dir . '/lang/admin/' . $lang . '.ini');
                } else {
                    $current = [];
                }

                foreach ($tmp as $key => $value) {
                    $modules[basename($dir)]['admin'][] = [
                        'key'       => $key,
                        'value'     => isset_or($current[$key], null),
                        'english'   => $value
                    ];
                }
            }
        }

        return $modules;
    }

    private function rglob($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, $this->rglob($dir . '/' . basename($pattern), $flags));
        }
        return $files;
    }

    private function directorySize($path): int
    {
        $bytestotal = 0;
        $path = realpath($path);
        if ($path !== false) {
            foreach (new RecursiveIteratorIterator(new RecursiveDotFilterIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS))) as $object) {
                try {
                    $bytestotal += $object->getSize();
                } catch (Exception $e) {
                }
            }
        }

        return $bytestotal;
    }

    private function roundSize($bytes): string
    {
        if ($bytes / 1024 < 1) {
            return $bytes . ' B';
        }
        if ($bytes / 1024 / 1024 < 1) {
            return round($bytes / 1024) . ' KB';
        }
        if ($bytes / 1024 / 1024 / 1024 < 1) {
            return round($bytes / 1024 / 1024, 2) . ' MB';
        } else {
            return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
        }
    }
}
