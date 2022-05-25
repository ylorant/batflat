<?php
/**
 * This file is part of Batflat ~ the lightweight, fast and easy CMS
 *
 * @license      https://batflat.org/license
 * @link         https://batflat.org
 */

return [
    'name'          =>  $core->lang['twitch']['module_name'],
    'description'   =>  $core->lang['twitch']['module_desc'],
    'author'        =>  'linkboss',
    'version'       =>  '1.0',
    'compatibility'	=> 	'1.3.*',       // Compatibility with Batflat version
    'icon'          =>  'twitch',
    'icon-style'    =>  'brand',

    'install'   => function () use ($core) {
        $core->db()->pdo()->exec("INSERT INTO `settings`
        (`module`, `field`, `value`)
        VALUES
        ('twitch', 'channel_name', null),
        ('twitch', 'client_id', null),
        ('twitch', 'client_secret', null)");
    },
    'uninstall' => function () use ($core) {
        $core->db()->pdo()->exec("DELETE FROM `settings` WHERE `module` = 'twitch'");
    }
];