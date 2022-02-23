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

    return [
        'name'          =>  $core->lang['sitemap']['module_name'],
        'description'   =>  $core->lang['sitemap']['module_desc'],
        'author'        =>  'Linkboss',
        'version'       =>  '1.0',
		'compatibility'	=> 	'1.3.*',
        'icon'          =>  'sitemap',

        'install'   => function () use ($core) {
            $core->db()->pdo()->exec("INSERT INTO `settings`
        (`module`, `field`, `value`)
        VALUES
        ('sitemap', 'noindex', 0)");
        },
        'uninstall' => function () use ($core) {
            $core->db()->pdo()->exec("DELETE FROM `settings` WHERE `module` = 'sitemap'");
        }
    ];
