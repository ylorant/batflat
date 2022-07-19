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

    $baseSlug = $core->getSettings('members_sta', 'slug');

    return [
        'name'          =>  $core->lang['members_sta']['module_name'],
        'description'   =>  $core->lang['members_sta']['module_desc'],
        'author'        =>  'linkboss',
        'version'       =>  '1.0',
		'compatibility'	=> 	'1.3.*',       // Compatibility with Batflat version
        'icon'          =>  'address-card',
        'icon-style'    =>  'regular',

        // Registering page for possible use as a homepage
        'pages'			=>  [$core->lang['members_sta']['module_name'] => $baseSlug],

        'install'       =>  function() use($core)
        {
            // Module settings
            $core->db()->pdo()->exec("INSERT INTO `settings` (`module`, `field`, `value`) VALUES
                ('members_sta', 'slug', null)"
            );
            // Members table
            $core->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `members_sta` (
                `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
                `name` text NOT NULL,
                `role` text NULL,
                `description` text NULL,
                `picture` text NULL,
                `twitch_handle` text NULL,
                `status` integer DEFAULT 0,
                `lang` text NOT NULL,
                `markdown` integer DEFAULT 0
            )");
            
            if (!is_dir(UPLOADS."/members_sta")) {
                mkdir(UPLOADS . "/members_sta", 0777);
            }
        },
        'uninstall'     =>  function() use($core)
        {
            $core->db()->pdo()->exec("DROP TABLE `members_sta`");
            deleteDir(UPLOADS."/members_sta");
        }
    ];