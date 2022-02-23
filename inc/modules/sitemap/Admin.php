<?php
/**
 * This file is part of Batflat ~ the lightweight, fast and easy CMS
 *
 * @author       Yohann Lorant
 */

namespace Inc\Modules\Sitemap;

use Exception;
use Inc\Core\AdminModule;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Exceptions\PhpfastcacheInvalidConfigurationException;
use ReflectionException;

/**
 * Sitemap admin class
 */
class Admin extends AdminModule
{
    private $assign = [];

    /**
     * Module navigation
     * Items of the returned array will be displayed in the administration sidebar
     *
     * @return array
     */
    public function navigation(): array
    {
        return [
            $this->lang('settings') => 'settings',
        ];
    }

    /**
     * GET: /admin/sitemap/settings
     * Manage Sitemap general configuration
     *
     * @return string
     * @throws Exception
     */
    public function getSettings()
    {
        $value = $this->settings('sitemap');

        $this->assign['noindex'] = $value['noindex'];

        return $this->draw('settings.html', ['sitemap' => $this->assign]);
    }

    /**
     * @throws PhpfastcacheInvalidConfigurationException
     * @throws ReflectionException
     */
    public function postSave()
    {
        $update = [
            'noindex' => $_POST['noindex']
        ];

        $errors = 0;
        foreach ($update as $field => $value) {
            if (!$this->db('settings')->where('module', 'sitemap')->where('field', $field)->save(['value' => $value])) {
                $errors++;
            }
        }

        if (!$errors) {
            // Clearing cache
            CacheManager::setDefaultConfig(new ConfigurationOption([
                'path' => __DIR__ . '/../../../tmp',
            ]));
            $this->notify('success', $this->lang('save_success'));
        } else {
            $this->notify('failure', $this->lang('save_failure'));
        }

        redirect(url([ADMIN, 'sitemap', 'settings']));
    }
}
