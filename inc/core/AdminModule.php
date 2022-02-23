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

/**
 * Admin class for administration panel
 */
abstract class AdminModule extends BaseModule
{
    /**
     * Module navigation
     *
     * @return array
     */
    public function navigation(): array
    {
        return [];
    }

    protected function addHeaderFiles()
    {
        // WYSIWYG plugin
        $this->core->addCSS(url('https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.18/summernote-bs4.css'));
        $this->core->addJS(url('https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.18/summernote-bs4.js'));

        // WYSIWYG language management
        if ($this->settings('settings.lang_admin') != 'en_english') {
            $this->core->addJS(url('inc/jscripts/wysiwyg/lang/' . $this->settings('settings.lang_admin') . '.js'));
        }

        // HTML & Markdown editor plugins
        $this->core->addCSS(url('/inc/jscripts/editor/markitup.min.css'));
        $this->core->addCSS(url('/inc/jscripts/editor/markitup.highlight.min.css'));
        $this->core->addCSS(url('/inc/jscripts/editor/sets/html/set.min.css'));
        $this->core->addCSS(url('/inc/jscripts/editor/sets/markdown/set.min.css'));
        $this->core->addJS(url('/inc/jscripts/editor/highlight.min.js'));
        $this->core->addJS(url('/inc/jscripts/editor/markitup.min.js'));
        $this->core->addJS(url('/inc/jscripts/editor/markitup.highlight.min.js'));
        $this->core->addJS(url('/inc/jscripts/editor/sets/html/set.min.js'));
        $this->core->addJS(url('/inc/jscripts/editor/sets/markdown/set.min.js'));

        // "ARE YOU SURE?" script
        $this->core->addJS(url('inc/jscripts/are-you-sure.min.js'));
    }
}
