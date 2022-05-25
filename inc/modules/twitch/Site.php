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

namespace Inc\Modules\Twitch;

use Exception;
use Inc\Core\SiteModule;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Helper\Psr16Adapter;
use TwitchClient\API\Auth\Authentication;
use TwitchClient\API\Helix\Helix;
use TwitchClient\API\Helix\Services\Streams;
use TwitchClient\Authentication\DefaultTokenProvider;

class Site extends SiteModule
{
    /**
     * @var string
     */
    protected $moduleDirectory = null;

    /**
     * Module initialization
     * Here everything is done while the module starts
     *
     * @throws Exception
     */
    public function init()
    {
        $twitchSettings =  $this->settings('twitch');
        $twitchEmbed = false;
        $isOnline = false;

        if(!empty($twitchSettings) && !empty($twitchSettings['channel_name']) && !empty($twitchSettings['client_id'])) {
            $online = $this->getOnlineData([$twitchSettings['channel_name']]);

            if(isset($online[0])) {
                $isOnline = true; 
                $twitchEmbed = $this->draw('embed.html', [
                    'twitch' => [
                        'channel_name' => $twitchSettings['channel_name'],
                        'parent_domain' => $_SERVER['HTTP_HOST']
                    ]
                ]);
            }
        }
        
        $this->tpl->set('twitchOnline', $isOnline);
        $this->tpl->set('twitchEmbed', $twitchEmbed);
        $this->tpl->set('twitchChannel', $twitchSettings['channel_name']);
        $this->moduleDirectory = MODULES.'/twitch';
        $this->core->addJS(url($this->moduleDirectory.'/assets/js/app.js'));
    }

    /**
     * Get one or multiple Twitch channels data (if they are online)
     *
     * @param array $channels
     * @return mixed|null
     * @throws Exception
     */
    public function getOnlineData($channels = [])
    {
        if (!empty($channels)) {
            static $cacheAdapter;
            
            if(empty($cacheAdapter)) {
                CacheManager::setDefaultConfig(new ConfigurationOption([
                    'path' => __DIR__ . '/../../../tmp', // or in windows "C:/tmp/"
                ]));
    
                $cacheAdapter = new Psr16Adapter('Files');
            }

            $data = [];
            $channelsKey = join('-', $channels);
            $tokenKey = "twitch-token";

            if(true || !$cacheAdapter->has($channelsKey)) {
                $twitchSettings =  $this->settings('twitch');

                $tokenProvider = new DefaultTokenProvider($twitchSettings['client_id'], $twitchSettings['client_secret']);
                $twitchAuth = new Authentication($tokenProvider);

                // Get
                if($cacheAdapter->has($tokenKey)) {
                    $token = $cacheAdapter->get($tokenKey);
                } else {
                    $token = $twitchAuth->getClientCredentialsToken();
                    $cacheAdapter->set($tokenKey, $token);
                }

                if(!empty($token)) {
                    $tokenProvider->setDefaultAccessToken($token['token']);
                    $tokenProvider->setDefaultRefreshToken($token['refresh']);
    
                    $helix = new Helix($tokenProvider);
                    /** @var Streams $streams */
                    $streamsApi = $helix->getService('streams');
    
                    $data = $streamsApi->getStreams([
                        Streams::FILTER_USER_LOGIN => [$twitchSettings['channel_name']]
                    ]);
    
                    $cacheAdapter->set($channelsKey, $data, 600);
                }
            } else {
                $data = $cacheAdapter->get($channelsKey, null);
            }

            return $data;
        }

        return null;
    }

    /**
     * Generate Twitch embed HTML tag to use it in BatFlat templates
     *
     * @return bool|string
     * @throws Exception
     */
    private function insertEmbed()
    {
        $tempAssign = [];
        $response = false;
        $twitchSettings =  $this->settings('twitch');

        if(!empty($twitchSettings) && !empty($twitchSettings['channel_name']) && !empty($twitchSettings['client_id'])) {
            $online = $this->getOnlineData([$twitchSettings['channel_name']]);
            if (isset($online[0])) {
                $tempAssign['channel_name'] = $twitchSettings['channel_name'];
                $response = $this->draw('embed.html', ['twitch' => $tempAssign]);
            }
        }

        return $response;
    }
}