<?php

class Activator
{
    /**
     * @return void
     */
    public static function make()
    {
        global $wpdb;
        $table_options = $wpdb->prefix . 'yandexid_webseed_options';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = ["
            CREATE TABLE $table_options (
            `id` INT NOT NULL AUTO_INCREMENT,
            `сlient_id` VARCHAR(32) NOT NULL,
            `client_secret` VARCHAR(32) NOT NULL,
            `button` BOOLEAN NOT NULL DEFAULT false,
            `container_id` VARCHAR(10) DEFAULT NULL,
            `widget` BOOLEAN NOT NULL DEFAULT true,
            `created_at` DATETIME DEFAULT NOW(),
            PRIMARY KEY (`id`)
        ) $charset_collate"];
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}