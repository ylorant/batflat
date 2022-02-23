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
use Inc\Core\Lib\QueryBuilder;
use Inc\Core\Lib\Router;
use Inc\Core\Lib\Settings;
use Inc\Core\Lib\Templates;

/**
 * Base class for each module functionality
 */
class BaseModule
{
    /**
     * Reference to Core instance
     *
     * @var Main
     */
    protected Main $core;

    /**
     * Reference to Template instance
     *
     * @var Templates
     */
    protected Templates $tpl;

    /**
     * Reference to Router instance
     *
     * @var Router
     */
    protected Router $route;

    /**
     * Reference to Settings instance
     *
     * @var Settings
     */
    protected Settings $settings;

    /**
     * Module dir name
     *
     * @var string
     */
    protected string $name;

    /**
     * Reference to language array
     *
     * @var array
     */
    protected array $lang;

    /**
     * @var Router
     */
    protected Router $router;

    /**
     * Module constructor
     *
     * @param Main $core
     */
    public function __construct(Main $core)
    {
        $this->core = $core;
        $this->tpl = $core->tpl;
        $this->router = $core->router;
        $this->settings = $core->settings;
        $this->lang = $core->lang;
        $this->name = strtolower(str_replace(['Inc\Modules\\', '\\Admin', '\\Site'], '', static::class));
    }

    /**
     * Module initialization
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * Procedures before destroy
     *
     * @return void
     */
    public function finish()
    {
    }

    /**
     * Languages list
     * @param string|null $selected
     * @param string $currentAttr ('active' or 'selected')
     * @param bool $all
     * @return array
     */
    protected function getLanguages(string $selected = null, string $currentAttr = 'active', bool $all = false): array
    {
        $langs = glob(BASE_DIR . '/inc/lang/*', GLOB_ONLYDIR);

        $result = [];
        foreach ($langs as $lang) {
            if (file_exists($lang . '/.lock')) {
                $active = false;

                if (!$all) {
                    continue;
                }
            } else {
                $active = true;
            }
            if ($selected == basename($lang)) {
                $attr = $currentAttr;
            } else {
                $attr = null;
            }
            $result[] = ['name' => basename($lang), 'attr' => $attr, 'active' => $active];
        }
        return $result;
    }

    /**
     * Hook to draw template with set variables
     *
     * @param string $file
     * @param array $variables
     * @return string
     */
    protected function draw(string $file, array $variables = []): string
    {
        if (!empty($variables)) {
            foreach ($variables as $key => $value) {
                $this->tpl->set($key, $value);
            }
        }

        if (strpos($file, BASE_DIR) !== 0) {
            if ($this instanceof AdminModule) {
                $file = MODULES . '/' . $this->name . '/view/admin/' . $file;
            } else {
                $file = MODULES . '/' . $this->name . '/view/' . $file;
            }
        }

        return $this->tpl->draw($file);
    }

    /**
     * Get current module language value
     *
     * @param string $key
     * @param string|null $module
     * @return mixed
     */
    protected function lang(string $key, string $module = null)
    {
        if (empty($module)) {
            $module = $this->name;
        }

        return isset_or($this->lang[$module][$key], null);
    }

    /**
     * Get or set module settings
     *
     * @param string $module Example 'module' or shorter 'module.field'
     * @param mixed $field If module has field it contains value
     * @param mixed $value OPTIONAL
     * @return bool|string
     * @throws Exception
     */
    protected function settings(string $module, $field = false, $value = false)
    {
        if (substr_count($module, '.') == 1) {
            $value = $field;
            list($module, $field) = explode('.', $module);
        }

        if ($value === false) {
            return $this->settings->get($module, $field);
        } else {
            return $this->settings->set($module, $field, $value);
        }
    }

    /**
     * Database QueryBuilder
     *
     * @param string|null $table
     * @return QueryBuilder
     */
    protected function db(string $table = null): QueryBuilder
    {
        return $this->core->db($table);
    }

    /**
     * Create notification
     * @return void
     */
    protected function notify()
    {
        call_user_func_array([$this->core, 'setNotify'], func_get_args());
    }
}
