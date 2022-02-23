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

    namespace Inc\Modules\Pagelist;

    use Inc\Core\SiteModule;
    use Inc\Core\Lib\Event;

class Site extends SiteModule
{
    public function init()
    {
        $slug = parseURL();
        $lang = $this->getLanguageBySlug($slug[0]);
        if ($lang !== false) {
            $this->core->loadLanguage($lang);
        }

        if (empty($slug[0]) || ($lang !== false && empty($slug[1]))) {
            $this->core->router->changeRoute($this->settings('settings', 'homepage'));
        }
    }

    public function routes()
    {
        $pagelists = $this->db('pagelist')->toArray();

        foreach ($pagelists as $pagelist) {
            $pageListSlug = $pagelist['slug'];
            // Load page lists from default language
            $this->route($pageListSlug, function () use ($pageListSlug) {
                $this->importPagelist($pageListSlug);
            });

            $this->route('(:str)/' . $pageListSlug, function ($lang) use ($pageListSlug) {
                // get current language by slug
                $lang = $this->getLanguageBySlug($lang);

                // Set current language to specified or if not exists to default
                if ($lang) {
                    $this->core->loadLanguage($lang);
                } else {
                    $slug = null;
                }

                $this->importPagelist($pageListSlug);
            });
        }
    }
    private function importPagelist($slug = null)
    {
        if (!empty($slug)) {
            $row = $this->db('pagelist')->where('slug', $slug)->where('lang', $this->getCurrentLang())->oneArray();

            if (empty($row)) {
                return Event::call('router.notfound');
            }
        } else {
            return Event::call('router.notfound');
        }

        // Get linked pages
        $linkedPages = $this->getListLinks($this->getCurrentLang());

        if (intval($row['markdown'])) {
            $parsedown = new \Inc\Core\Lib\Parsedown();
            $row['content'] = $parsedown->text($row['content']);
        }

        foreach ($linkedPages[$row['id']] as &$linkedPage) {
            $wrapped = wordwrap(strip_tags($linkedPage['desc']), 80);
            $lines = explode("\n", $wrapped);
            $linkedPage['summary'] = $lines[0];
            if (key_exists(1, $lines)) {
                $linkedPage['summary'] = $linkedPage['summary'] . ' ...';
            }
        }

        $this->setTemplate($row['template']);
        $this->tpl->set('page', $row);
        $this->tpl->set('subpages', $linkedPages[$row['id']]);
    }

    protected function getListLinks($lang)
    {
        $pages = $this->db('pagelist_pages')
                      ->select('pages.*')
                      ->select([
                          'pl_id' => 'pagelist.id',
                          'plp_position' => 'pagelist_pages.position',
                          'picture' => 'pagelist_pages.picture'
                      ])
                      ->join('pagelist', 'pagelist.id = pagelist_pages.pagelist')
                      ->join('pages', 'pagelist_pages.page = pages.id')
                      ->where('pagelist.lang', $lang)
                      ->asc('pagelist_pages.position')
                      ->toArray();

        $sortFunction = function ($a, $b) {
            return $a['plp_position'] - $b['plp_position'];
        };

            usort($pages, $sortFunction);

            $pagesByPagelist = [];
        foreach ($pages as $page) {
            if (!isset($pagesByPagelist[$page['pl_id']])) {
                $pagesByPagelist[$page['pl_id']] = [];
            }

            $pagesByPagelist[$page['pl_id']][] = $page;
        }

            return $pagesByPagelist;
    }

    protected function getCurrentLang()
    {
        if (!isset($_SESSION['lang'])) {
            return $this->settings('settings', 'lang_site');
        } else {
            return $_SESSION['lang'];
        }
    }

    protected function getLanguageBySlug($slug)
    {
        $langs = parent::getLanguages();
        foreach ($langs as $lang) {
            preg_match_all('/([a-z]{2})_([a-z]+)/', $lang['name'], $matches);
            if ($slug == $matches[1][0]) {
                return $matches[0][0];
            }
        }

        return false;
    }
}
