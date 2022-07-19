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

$baseSlug = $core->getSettings('events_registration', 'slug');

return [
    'name'          =>  $core->lang['events_registration']['module_name'],
    'description'   =>  $core->lang['events_registration']['module_desc'],
    'author'        =>  'linkboss',
    'version'       =>  '1.0',
    'compatibility' =>  '1.3.*',       // Compatibility with Batflat version
    'icon'          =>  'clipboard',
    'icon-style'    =>  'regular',

    // Registering page for possible use as a homepage
    'pages'         =>  [$core->lang['events_registration']['module_name'] => $baseSlug],

    'install'       =>  function () use ($core) {
        // Module settings
        $core->db()->pdo()->exec("INSERT INTO `settings` (`module`, `field`, `value`) VALUES
                ('events_registration', 'slug', null),
                ('events_registration', 'description', null)");

        // Events table
        $core->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `events_registration` (
                `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                `runner_name` TEXT NOT NULL,
                `game_name` TEXT NOT NULL,
                `game_category` TEXT NOT NULL,
                `estimated_time` INTEGER NOT NULL,
                `race` INTEGER NOT NULL DEFAULT 0,
                `race_opponents` TEXT NULL,
                `event_id` INTEGER NULL,
                `status` INTEGER NOT NULL DEFAULT 0,
				`created_at` INTEGER NOT NULL,
                `comment` TEXT NULL
            )");
    },
    'uninstall'     =>  function () use ($core) {
        $core->db()->pdo()->exec("DELETE FROM `settings` WHERE `module` = 'events_registration'");
        $core->db()->pdo()->exec("DROP TABLE `events_registration`");
        deleteDir(UPLOADS . "/events");
    }
];
