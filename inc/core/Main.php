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

namespace Inc\Core;

use Exception;
use Inc\Core\Lib\ModulesCollection;
use Inc\Core\Lib\QueryBuilder;
use Inc\Core\Lib\Templates;
use Inc\Core\Lib\Router;
use Inc\Core\Lib\Settings;
use Inc\Core\Lib\License;

/**
 * Base for core classes
 */
abstract class Main
{
    /**
     * Language array
     *
     * @var array
     */
    public array $lang = [];

    /**
     * Templates instance
     *
     * @var Templates
     */
    public Templates $tpl;

    /**
     * Router instance
     *
     * @var Router
     */
    public Router $router;

    /**
     * Settings instance
     *
     * @var Settings
     */
    public Settings $settings;

    /**
     * List of additional header or footer content
     *
     * @var array
     */
    public array $appends = [];

    /**
     * Reference to ModulesCollection
     *
     * @var ModulesCollection|null
     */
    public ?ModulesCollection $module = null;

    /**
     * Settings cache
     *
     * @var array
     */
    protected static array $settingsCache = [];

    /**
     * User cache
     *
     * @var array
     */
    protected static array $userCache = [];

    /**
     * Main constructor
     */
    public function __construct()
    {
        $this->setSession();

        $dbFile = BASE_DIR . '/inc/data/database.sdb';

        if (file_exists($dbFile)) {
            QueryBuilder::connect("sqlite:{$dbFile}");
        } else {
            $this->freshInstall($dbFile);
        }

        $this->settings = new Settings($this);
        date_default_timezone_set($this->settings->get('settings.timezone'));

        $this->tpl = new Templates($this);
        $this->router = new Router();

        $this->append(base64_decode('PG1ldGEgbmFtZT0iZ2VuZXJhdG9yIiBjb250ZW50PSJCYXRmbGF0IiAvPg=='), 'header');
    }

    /**
     * New instance of QueryBuilder
     *
     * @param string|null $table
     * @return QueryBuilder
     */
    public function db(string $table = null): QueryBuilder
    {
        return new QueryBuilder($table);
    }

    /**
     * get module settings
     * @param string $module
     * @param string|null $field
     * @param bool $refresh
     *
     * @return string or array
     *
     * @deprecated
     *
     */
    public function getSettings(string $module = 'settings', string $field = null, bool $refresh = false): string
    {
        if ($refresh) {
            $this->settings->reload();
        }

        return $this->settings->get($module, $field);
    }

    /**
     * Set module settings value
     *
     * @param string $module
     * @param string $field
     * @param string $value
     *
     * @return bool
     * @throws Exception
     * @deprecated
     *
     */
    public function setSettings(string $module, string $field, string $value): bool
    {
        return $this->settings->set($module, $field, $value);
    }

    /**
     * safe session
     * @return void
     */
    private function setSession()
    {
        ini_set('session.use_only_cookies', 1);
        session_name('bat');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (batflat_dir() === '/' ? '/' : batflat_dir() . '/')
        ]);
        session_start();
    }

    /**
     * create notification
     * @param string $type ('success' or 'failure')
     * @param string $text
     * @param mixed $args [, mixed $... ]]
     * @return void
     */
    public function setNotify(string $type, string $text, $args = null)
    {
        $variables = [];
        $numargs = func_num_args();
        $arguments = func_get_args();

        if ($numargs > 1) {
            for ($i = 1; $i < $numargs; $i++) {
                $variables[] = $arguments[$i];
            }
            $text = call_user_func_array('sprintf', $variables);
            $_SESSION[$arguments[0]] = $text;
        }
    }

    /**
     * display notification
     * @return array|false
     */
    public function getNotify()
    {
        if (isset($_SESSION['failure'])) {
            $result = ['text' => $_SESSION['failure'], 'type' => 'danger'];
            unset($_SESSION['failure']);
            return $result;
        } elseif (isset($_SESSION['success'])) {
            $result = ['text' => $_SESSION['success'], 'type' => 'success'];
            unset($_SESSION['success']);
            return $result;
        } else {
            return false;
        }
    }

    /**
     * adds CSS URL to array
     * @param string $path
     * @return void
     */
    public function addCSS(string $path)
    {
        $this->appends['header'][] = "<link rel=\"stylesheet\" href=\"$path\">\n";
    }

    /**
     * adds JS URL to array
     * @param string $path
     * @param string $location (header / footer)
     * @return void
     */
    public function addJS(string $path, string $location = 'header')
    {
        $this->appends[$location][] = "<script src=\"$path\"></script>\n";
    }

    /**
     * adds string to array
     * @param string $string
     * @param string $location (header / footer)
     * @return void
     */
    public function append(string $string, string $location)
    {
        $this->appends[$location][] = $string . "\n";
    }

    /**
     * Batflat license verify
     * By removing or modifing these procedures you break our license.
     *
     * @param string $buffer
     * @return string
     */
    public static function verifyLicense(string $buffer): string
    {
        $core = isset_or($GLOBALS['core'], false);
        if (!$core) {
            return $buffer;
        }
        $checkBuffer = preg_replace('/<!--(.|\s)*?-->/', '', $buffer);
        $isHTML = strpos(get_headers_list('Content-Type'), 'text/html') !== false;
        $hasBacklink = strpos(
            $checkBuffer,
            $core->lang['general']['powered_by'] . ' <a href="https://batflat.org/">Batflat</a>'
        ) !== false;
        $hasHeader = get_headers_list('X-Created-By') === 'Batflat <batflat.org>';
        $license = License::verify($core->settings->get('settings.license'));
        if (($license == License::FREE) && $isHTML && (!$hasBacklink || !$hasHeader)) {
            return '<strong>Batflat license system</strong><br />The return link has been deleted or modified.';
        } elseif ($license == License::TIME_OUT) {
            return $buffer . '<script>alert("Batflat license system\nCan\'t connect to license server and verify it.");</script>';
        } elseif ($license == License::ERROR) {
            return '<strong>Batflat license system</strong><br />The license is not valid. Please correct it or go to free version.';
        }

        return trim($buffer);
    }

    /**
     * check if user is login
     * @return bool
     */
    public function loginCheck(): bool
    {
        if (isset($_SESSION['bat_user']) && isset($_SESSION['token']) && isset($_SESSION['userAgent']) && isset($_SESSION['IPaddress'])) {
            if ($_SESSION['IPaddress'] != $_SERVER['REMOTE_ADDR']) {
                return false;
            }
            if ($_SESSION['userAgent'] != $_SERVER['HTTP_USER_AGENT']) {
                return false;
            }

            if (empty(parseURL(1))) {
                redirect(url([ADMIN, 'dashboard', 'main']));
            } elseif (!isset($_GET['t']) || ($_SESSION['token'] != @$_GET['t'])) {
                return false;
            }

            return true;
        } elseif (isset($_COOKIE['batflat_remember'])) {
            $token = explode(":", $_COOKIE['batflat_remember']);
            if (count($token) == 2) {
                $row = $this->db('users')->leftJoin('remember_me', 'remember_me.user_id = users.id')->where('users.id', $token[0])->where('remember_me.token', $token[1])->select(['users.*', 'remember_me.expiry', 'token_id' => 'remember_me.id'])->oneArray();

                if ($row) {
                    if (time() - $row['expiry'] > 0) {
                        $this->db('remember_me')->delete('id', $row['token_id']);
                    } else {
                        $_SESSION['bat_user']   = $row['id'];
                        $_SESSION['token']      = bin2hex(openssl_random_pseudo_bytes(6));
                        $_SESSION['userAgent']  = $_SERVER['HTTP_USER_AGENT'];
                        $_SESSION['IPaddress']  = $_SERVER['REMOTE_ADDR'];

                        $this->db('remember_me')->where('remember_me.user_id', $token[0])->where('remember_me.token', $token[1])->save(['expiry' => time() + 60 * 60 * 24 * 30]);

                        if (strpos($_SERVER['SCRIPT_NAME'], '/' . ADMIN . '/') !== false) {
                            redirect(url([ADMIN, 'dashboard', 'main']));
                        }

                        return true;
                    }
                }
            }
            setcookie('batflat_remember', null, -1, '/');
        }

        return false;
    }

    /**
     * get user informations
     * @param string $field
     * @param int|null $id (optional)
     * @param bool $refresh (optional)
     * @return string
     */
    public function getUserInfo(string $field, int $id = null, bool $refresh = false): ?string
    {
        if (!$id) {
            $id = isset_or($_SESSION['bat_user'], 0);
        }

        if (empty(self::$userCache) || $refresh) {
            $request = $this->db('users')->where('id', $id)->oneArray();
            self::$userCache = is_array($request) ? $request : [];
        }

        if (empty(self::$userCache) || empty(self::$userCache[$field])) {
            return null;
        }

        return self::$userCache ? self::$userCache[$field] : null;
    }

    /**
     * Load installed modules
     *
     * @return void
     */
    public function loadModules()
    {
        if ($this->module == null) {
            $this->module = new Lib\ModulesCollection($this);
        }
    }

    /**
     * Generating database with Batflat data
     * @param string $dbFile path to Batflat SQLite database
     * @return void
     */
    private function freshInstall(string $dbFile)
    {
        QueryBuilder::connect("sqlite:{$dbFile}");
        $pdo = QueryBuilder::pdo();

        $core = $this;

        $modules = unserialize(BASIC_MODULES);
        foreach ($modules as $module) {
            $file = MODULES . '/' . $module . '/Info.php';

            if (file_exists($file)) {
                $this->lang[$module] = parse_ini_file(MODULES . '/' . $module . '/lang/admin/en_english.ini');

                $info = include($file);
                if (isset($info['install'])) {
                    $info['install']();
                }
            }
        }

        foreach ($modules as $order => $name) {
            $core->db('modules')->save(['dir' => $name, 'sequence' => $order]);
        }


        redirect(url());
    }
}
