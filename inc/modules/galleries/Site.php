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

namespace Inc\Modules\Galleries;

use Inc\Core\SiteModule;

class Site extends SiteModule
{
    /** @var string */
    protected string $moduleDirectory = MODULES . '/galleries';

    public function init()
    {
        $this->importGalleries();
    }

    private function importGalleries()
    {
        $assign = [];
        $galleries = $this->db('galleries')->toArray();

        if (count($galleries)) {
            foreach ($galleries as $gallery) {
                if ($gallery['sort'] == 'ASC') {
                    $items = $this->db('galleries_items')->where('gallery', $gallery['id'])->asc('id')->toArray();
                } else {
                    $items = $this->db('galleries_items')->where('gallery', $gallery['id'])->desc('id')->toArray();
                }

                $tempAssign = $gallery;

                if (count($items)) {
                    foreach ($items as &$item) {
                        $item['src'] = unserialize($item['src']);

                        if (!isset($item['src']['sm'])) {
                            $item['src']['sm'] = isset($item['src']['xs']) ? $item['src']['xs'] : $item['src']['lg'];
                        }
                    }

                    $tempAssign['items'] = $items;

                    $assign[$gallery['slug']] = $this->draw('gallery.html', ['gallery' => $tempAssign]);
                }
            }
        }
        $this->tpl->set('gallery', $assign);

        $this->core->addCSS(url('https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css'));
        $this->core->addCSS(url($this->moduleDirectory . '/assets/css/galleries.css'));
        $this->core->addJS(url('https://cdn.jsdelivr.net/gh/mcstudios/glightbox/dist/js/glightbox.min.js'));
    }
}
