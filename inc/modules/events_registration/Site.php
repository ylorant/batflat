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

namespace Inc\Modules\Events_Registration;

use Exception;
use Inc\Core\SiteModule;

/**
 * events_registration site class
 */
class Site extends SiteModule
{
    public const DEFAULT_SLUG = 'register';

    /** @var string $baseSlug */
    protected $baseSlug = self::DEFAULT_SLUG;

    /** @var string */
    protected $moduleDirectory = null;

    /** @var string */
    protected $timeZone = 'Europe/Paris';

    /** @var string|null */
    private $error = null;

    public $assign = [];

    /**
     * Module initialization
     * Here everything is done while the module starts
     *
     * @return void
     * @throws Exception
     */
    public function init()
    {
        $settingsBaseSlug = $this->settings('events_registration.slug');
        $this->baseSlug = $settingsBaseSlug ? ltrim($settingsBaseSlug, '/') : $this->baseSlug;

        $this->moduleDirectory = MODULES . '/events_registration';
        $this->core->addCSS(url($this->moduleDirectory . '/assets/css/events_registration.css'));
        $this->core->addJS(url($this->moduleDirectory . '/assets/js/events_registration.js'));
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
     * @return string
     * @throws Exception
     */
    protected function getCurrentLang()
    {
        $lang = $_SESSION['lang'];
        if (!$lang) {
            $lang = $this->settings('settings', 'lang_site');
        }

        return mb_substr($lang, 0, 2);
    }

    /**
     * GET: /index
     * Display main page including form
     *
     * @throws Exception
     */
    public function getIndex()
    {
        $settings = $this->settings('events_registration');

        $this->assign['locale'] = $this->getCurrentLang();
        $this->assign['time_zone'] = $this->timeZone;
        $this->assign['events'] = $this->getAvailableEvents();
        $this->assign['description'] = $settings['description'] ?? "";
        $this->addHeaderFiles();

        if (!is_null($this->error)) {
            $this->notify('failure', $this->error);
        }

        $page = [
            'title' => $this->lang('title'),
            'desc' => $this->lang('desc'),
            'content' => $this->draw('register.html', $this->assign)
        ];

        $this->setTemplate('index.html');

        if (isset($_POST['send-registration'])) {
            $data = $_POST;
            htmlspecialchars_array($data);
            if (!$this->checkErrors($data)) {
                if ($this->postSaveRegistration($data)) {
                    $this->notify('success', $this->lang('send_success'));
                } else {
                    $this->notify('failure', $this->error);
                }
            } else {
                $this->notify('failure', $this->error);
            }

            redirect(currentURL());
        }

        $this->tpl->set('page', $page);
    }

    /**
     * @param $array
     * @return bool
     */
    private function checkErrors($array): bool
    {
        $setError = false;
        if (checkEmptyFields(['runner_name', 'game_name', 'game_category', 'estimated_time', 'duration'], $array)) {
            $this->error = $this->lang('empty_inputs');
            $setError = true;
        }
        return $setError;
    }

    /**
     * Saves the registration.
     * 
     * @param array $registrationData
     * @return bool
     */
    private function postSaveRegistration($registrationData): bool
    {
        $saveSuccess = true;

        $query = $this->db('events_registration')->save([
            'runner_name' => $registrationData['runner_name'],
            'game_name' => $registrationData['game_name'],
            'game_category' => $registrationData['game_category'],
            'estimated_time' => $registrationData['duration'],
            'race' => $registrationData['race'] ?? 0,
            'race_opponents' => $registrationData['race_opponents'],
            'event_id' => $registrationData['event'] != -1 ? $registrationData['event'] : null,
            'comment' => $registrationData['comment']
        ]);
        if (!$query) {
            $this->error = $this->lang('save_failure');
            $saveSuccess = false;
        }
        return $saveSuccess;
    }

    private function getAvailableEvents(): array
    {
        $fields = [
            'id',
            'name',
            'start_at',
            'end_at'
        ];

        $events = $this->db('events')
            ->where('lang', $_SESSION['lang'])
            ->where('registration', 1)
            ->where('end_at', '<=', date())
            ->select($fields)
            ->toArray();

        foreach ($events as $key => $event) {
            $events[$key]['start_at'] = date('d-m-Y', $event['start_at']);
            $events[$key]['end_at'] = date('d-m-Y', $event['start_at']);
        }

        return $events;
    }

    private function addHeaderFiles()
    {
        $this->core->addCSS(
            url('https://cdn.jsdelivr.net/npm/@tarekraafat/autocomplete.js@7.2.0/dist/css/autoComplete.min.css')
        );
        $this->core->addJS(
            url('https://cdn.jsdelivr.net/npm/@tarekraafat/autocomplete.js@7.2.0/dist/js/autoComplete.min.js')
        );
    }
}
