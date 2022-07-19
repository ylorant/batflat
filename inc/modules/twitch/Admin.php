<?php
/**
 * This file is part of Batflat ~ the lightweight, fast and easy CMS
 *
 * @author       Yohann Lorant
 */

namespace Inc\Modules\Twitch;

use Exception;
use Inc\Core\AdminModule;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Exceptions\PhpfastcacheInvalidConfigurationException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Phpfastcache\Helper\Psr16Adapter;
use ReflectionException;

/**
 * Twitch admin class
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
     * GET: /admin/twitch/settings
     * Manage Twitch general configuration
     *
     * @return string
     * @throws Exception
     */
    public function getSettings()
    {
        $value = $this->settings('twitch');

        $this->assign['channel_name'] = $value['channel_name'];
        $this->assign['client_id'] = $value['client_id'];
        $this->assign['client_secret'] = $value['client_secret'];

        return $this->draw('settings.html', ['twitch' => $this->assign]);
    }

    /**
     * @throws PhpfastcacheInvalidConfigurationException
     * @throws PhpfastcacheSimpleCacheException
     * @throws ReflectionException
     */
    public function postSave()
    {
        $update = [
            'channel_name' => $_POST['channel_name'],
            'client_id' => $_POST['client_id'],
            'client_secret' => $_POST['client_secret']
        ];

        $errors = 0;
        foreach ($update as $field => $value) {
            if (!$this->db('settings')->where('module', 'twitch')->where('field', $field)->save(['value' => $value])) {
                $errors++;
            }
        }

        if (!$errors) {
            // Clearing cache
            CacheManager::setDefaultConfig(new ConfigurationOption([
                'path' => __DIR__ . '/../../../tmp',
            ]));

            $cacheAdapter = new Psr16Adapter('Files');
            $cacheAdapter->delete('twitch-status');

            $this->notify('success', $this->lang('save_success'));
        } else {
            $this->notify('failure', $this->lang('save_failure'));
        }

        redirect(url([ADMIN, 'twitch', 'settings']));
    }
}
