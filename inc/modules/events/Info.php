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

$baseSlug = $core->getSettings('events', 'slug');

return [
    'name'          =>  $core->lang['events']['module_name'],
    'description'   =>  $core->lang['events']['module_desc'],
    'author'        =>  'linkboss',
    'version'       =>  '1.0',
    'compatibility' =>  '1.3.*',       // Compatibility with Batflat version
    'icon'          =>  'calendar-alt',
    'icon-style'    =>  'regular',

    // Registering page for possible use as a homepage
    'pages'         =>  [$core->lang['events']['module_name'] => $baseSlug],
    'install'       =>  function () use ($core) {
        // Module settings
        $core->db()->pdo()->exec("INSERT INTO `settings` (`module`, `field`, `value`) VALUES
            ('events', 'slug', null),
            ('events', 'event_slug', null)");
        // Events table
        $core->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `events` (
            `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            `name` TEXT NOT NULL,
            `start_at` INTEGER NOT NULL,
            `end_at` INTEGER NOT NULL,
            `description` TEXT NULL,
            `picture` TEXT NULL,
            `building_name` TEXT NULL,
            `building_address` TEXT NULL,
            `latitude` REAL NULL,
            `longitude` REAL NULL,
            `channel_name` TEXT NULL,
            `horaro_event_id` TEXT NULL,
            `horaro_schedule_id` TEXT NULL,
            `lang` TEXT NOT NULL,
            `markdown` INTEGER DEFAULT 0,
            `group_id` INTEGER NULL,
            `registration` INTEGER DEFAULT 0,
            `updated_at` INTEGER NOT NULL,
            `created_at` INTEGER NOT NULL,
            `published_at`	INTEGER DEFAULT 0
        )");
        $core->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `events_groups` (
            `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
            `lang` TEXT NOT NULL,
            `name` text NOT NULL,
            `color` text NULL,
            `textColor` text NULL
        )");
        if (!is_dir(UPLOADS . "/events")) {
            mkdir(UPLOADS . "/events", 0777);
        }
    },
    'uninstall'     =>  function () use ($core) {
        $core->db()->pdo()->exec("DELETE FROM `settings` WHERE `module` = 'events'");
        $core->db()->pdo()->exec("DROP TABLE `events`");
        $core->db()->pdo()->exec("DROP TABLE `events_groups`");
        deleteDir(UPLOADS . "/events");
    }
];
