<?php
/**
 * @since             1.0.0
 * @package           Authorization via Yandex ID
 *
 * @wordpress-plugin
 * Plugin Name:       Плагин для авторизации в Яндекс ID
 * Plugin URI:        https://webseed.ru/avtorizacziya-cherez-yandeks-id-na-wordpress-plagin-dlya-vhoda
 * Description:       Укажите Client Token и Secret Token в настройках плагина.
 * Version:           1.0.0
 * Author:            Nikita Ivanov (Nick Iv)
 * Author URI:        https://github.com/dllpl
 * License:           BSD 3-Clause License
 * License URI:       https://github.com/dllpl/yandexid-wp-plugin?tab=BSD-3-Clause-1-ov-file
 */

if (!defined('WPINC')) {
    die;
}

add_action('rest_api_init', 'register_routes');

add_action( 'wp_head', 'add_script_to_head' );
add_action( 'wp_footer', 'add_script_to_footer' );

add_action('admin_menu', 'admin_menu_init');

register_activation_hook(__FILE__, 'activate');
register_uninstall_hook(__FILE__, 'uninstall');

/** Регистрация REST API методов плагина */
function register_routes()
{
    require_once plugin_dir_path(__FILE__) . 'app/Controllers/MainRequestController.php';
    $controller = new MainRequestController();
    $controller->registerRoutes();
}

function admin_menu_init()
{
    require_once plugin_dir_path(__FILE__) . 'admin/AdminController.php';
    $option = new AdminController();
    $option->addMenu();
}
function add_script_to_head() {

    if (!is_user_logged_in()) {
        wp_enqueue_script( 'sdk-suggest-with-polyfills-latest', 'https://yastatic.net/s3/passport-sdk/autofill/v1/sdk-suggest-with-polyfills-latest.js', [], '');
    }

}

function add_script_to_footer() {

    if (!is_user_logged_in()) {
        require_once plugin_dir_path(__FILE__) . 'app/Controllers/PublicController.php';

        $public = new PublicController();
        $public->scriptInit();
    }
}


function activate()
{
    require_once plugin_dir_path(__FILE__) . 'includes/Activator.php';
    Activator::make();
}

function uninstall()
{
    require_once plugin_dir_path(__FILE__) . 'includes/Uninstall.php';
    Uninstall::make();
}