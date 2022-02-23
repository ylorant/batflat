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

namespace Inc\Core\Lib;

use Exception;
use Inc\Core\Main;

/**
 * Batflat modules settings
 */
class Settings
{
    /**
     * Instance of core class
     *
     * @var Main
     */
    protected Main $core;

    /**
     * Cached settings variables
     *
     * @var array
     */
    protected array $cache = [];

    /**
     * Settings constructor
     *
     * @param Main $core
     */
    public function __construct(Main $core)
    {
        $this->core = $core;
        $this->reload();
    }

    /**
     * Get all settings
     *
     * @return array
     */
    public function all(): array
    {
        return $this->cache;
    }

    /**
     * Fetch fresh data from database
     *
     * @return void
     */
    public function reload()
    {
        $results = $this->core->db('settings')->toArray();
        foreach ($results as $result) {
            $this->cache[$result['module']][$result['field']] = $result['value'];
        }
    }

    /**
     * Get specified field
     *
     * @param string $module Example 'module' or shorter 'module.field'
     * @param string $field OPTIONAL
     * @return mixed
     */
    public function get(string $module, $field = false)
    {
        if (substr_count($module, '.') == 1) {
            list($module, $field) = explode('.', $module);
        }

        if (empty($field)) {
            return $this->cache[$module];
        }

        return $this->cache[$module][$field];
    }

    /**
     * Save specified settings value
     *
     * @param string $module Example 'module' or shorter 'module.field'
     * @param string $field If module has field it contains value
     * @param string $value OPTIONAL
     * @return bool
     * @throws Exception
     */
    public function set(string $module, string $field, $value = false): bool
    {
        if (substr_count($module, '.') == 1) {
            $value = $field;
            list($module, $field) = explode('.', $module);
        }

        if ($value === false) {
            throw new Exception('Value cannot be empty');
        }

        if ($this->core->db('settings')->where('module', $module)->where('field', $field)->save(['value' => $value])) {
            $this->cache[$module][$field] = $value;
            return true;
        }

        return false;
    }
}
