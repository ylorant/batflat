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

    /**
     * @throws Exception
     */
    protected function addHeaderFiles()
    {
        // WYSIWYG plugin
        $this->core->addCSS(url('https://cdn.jsdelivr.net/npm/suneditor@latest/dist/css/suneditor.min.css'));
        $this->core->addJS(url('https://cdn.jsdelivr.net/npm/suneditor@latest/dist/suneditor.min.js'));

        // WYSIWYG language management
        if ($this->settings('settings.lang_admin') != 'en_english') {
            $this->core->addJS(url(
                'https://cdn.jsdelivr.net/npm/suneditor@latest/src/lang/' .
                mb_substr($this->settings('settings.lang_admin'), 0, 2) . '.js'
            ));
        }

        // HTML & Markdown editor plugins
        $this->core->addCSS(url('https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css'));
        $this->core->addJS(url('https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js'));
    }
}
