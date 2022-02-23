<?php
    /**
    * This file is a third party module for Batflat ~ the lightweight, fast and easy CMS
    * 
    * @author       Yohann Lorant <yohann.lorant@gmail.com>
    * @copyright    2017 Yohann Lorant
    * @license      MIT License
    * @link         http://nyan.at
    */
    
    return [
        'name'          =>  $core->lang['pagelist']['module_name'],
        'description'   =>  $core->lang['pagelist']['module_desc'],
        'author'        =>  'Yohann Lorant',
        'version'       =>  '1.0',
        'compatibility'	=> 	'1.3.*',
        'icon'          =>  'copy',

        'install'       =>  function() use($core)
        {
            $core->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `pagelist` (
                `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
                `title` text NOT NULL,
                `description` text NULL,
                `content` text NULL,
                `lang` text NOT NULL,
                `markdown` integer NOT NULL,
                `template` text NOT NULL,
                `slug` text NOT NULL,
                `updated_at` DATE NOT NULL DEFAULT CURRENT_TIMESTAMP 
            )");
            $core->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `pagelist_pages` (
                `pagelist` integer NOT NULL,
                `page` integer NOT NULL,
                `picture` text NULL,
                `position` integer NOT NULL,
                 PRIMARY KEY(`pagelist`, `page`)
            )");
            
            if(!is_dir(UPLOADS."/pagelist"))
                mkdir(UPLOADS."/pagelist", 0777);
        },
        'uninstall'     =>  function() use($core)
        {
            $core->db()->pdo()->exec("DROP TABLE `pagelist`");
            $core->db()->pdo()->exec("DROP TABLE `pagelist_pages`");
            
            deleteDir(UPLOADS."/pagelist");
        }
    ];