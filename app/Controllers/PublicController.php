<?php

require_once plugin_dir_path(__FILE__) . '../../includes/Options.php';

class PublicController
{
    use Options;

    private $options;

    public function __construct()
    {
        $options = Options::getOptions();
        $this->options = $options ?? null;
    }

    public function scriptInit()
    {
        $options = $this->options;

        if ($options && is_array($options) && !empty($options['сlient_id'] && !empty($options['client_secret']))) {

            wp_enqueue_script('yandexid_webseed', plugins_url('../../public/button_and_widget.js', __FILE__), [],
                filemtime(plugin_dir_path(__FILE__) . '../../public/button_and_widget.js'), 'in_footer');

            wp_add_inline_script('yandexid_webseed_button', 'const yaWpData = ' . wp_json_encode($options), 'before');


        } else {
            wp_add_inline_script('yandexid_webseed',
                'const yaWpData = ' . wp_json_encode(['error' => 'Задайте настройки плагина Яндекс ID, чтобы начать работу']), 'before');
        }
    }
}
