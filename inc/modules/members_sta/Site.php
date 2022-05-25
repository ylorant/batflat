<?php

    namespace Inc\Modules\Members_Sta;

    use Exception;
    use Inc\Core\SiteModule;
    use Inc\Modules\Twitch\Site as Twitch;

/**
 * members_sta site class
 */
class Site extends SiteModule
{
    private const DEFAULT_SLUG = 'members';

    /** @var string $baseSlug */
    protected string $baseSlug = self::DEFAULT_SLUG;

    public array $assign = [];

    /**
     * Module initialization
     * Here everything is done while the module starts
     *
     * @return void
     */
    public function init()
    {
        $settingsBaseSlug = $this->settings('members_sta.slug');
        $this->baseSlug = $settingsBaseSlug ? ltrim($settingsBaseSlug, '/') : $this->baseSlug;
    }

    /**
     * Register module routes
     * Call the appropriate method/function based on URL
     *
     * @return void
     * @throws Exception
     */
    public function routes()
    {
        $this->route($this->baseSlug, 'getIndex');
    }

        /**
         * GET: /index
         * Display main page including members information
         *
         * @throws Exception
         */
    public function getIndex()
    {
        $membersWithRoles = $this->db('members_sta')
            ->where('status', 1)->where('lang', $this->getCurrentLang())->where('role', '!=', '')
            ->asc('role')
            ->asc('name')
            ->toArray();
        $membersWithoutRoles = $this->db('members_sta')
            ->where('status', 1)->where('lang', $this->getCurrentLang())->where('role', '')
            ->asc('name')
            ->toArray();
        $members = array_merge($membersWithRoles, $membersWithoutRoles);

        $this->assign['members'] = [];
        $channelNames = [];
        $channelMembers = [];

        foreach ($members as $member) {
            $memberKey = strtolower($member['name']);
            $handleKey = strtolower($member['twitch_handle']);

            // Used after for Twitch status
            $channelNames[] = $handleKey;
            $channelMembers[$handleKey] = $memberKey;
            $member['online'] = 0;

            if (intval($member['markdown'])) {
                $parsedown = new \Inc\Core\Lib\Parsedown();
                $member['description'] = $parsedown->text($member['description']);
            }
            if (isset($member['picture'])) {
                $member['picture'] = url(UPLOADS . '/members_sta/' . $member['picture']);
            } else {
                $member['picture'] = url(MODULES . '/members_sta/img/default.png');
            }

            $this->assign['members'][$memberKey] = $member;
        }

        // Check all Twitch status in one call (to preserve Twitch API call rate limit)
        $twitch = new Twitch($this->core);
        $twitchData = $twitch->getOnlineData($channelNames);
        if (isset($twitchData)) {
            foreach ($twitchData as $channel) {
                // Only update data if channel actually present in the members list
                if ($channel) {
                    $handleKey = strtolower($channel->user_name);

                    if (array_key_exists($handleKey, $channelMembers)) {
                        $this->assign['members'][$channelMembers[$handleKey]]['online'] = 1;
                    }
                }
            }
        }

        $page = [
        'title' => $this->lang('title'),
        'desc' => $this->lang('desc'),
        'content' => $this->draw('members.html', $this->assign)
        ];

        $this->setTemplate('index.html');
        $this->tpl->set('page', $page);
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function getCurrentLang()
    {
        if (!isset($_SESSION['lang'])) {
            return $this->settings('settings', 'lang_site');
        } else {
            return $_SESSION['lang'];
        }
    }
}
