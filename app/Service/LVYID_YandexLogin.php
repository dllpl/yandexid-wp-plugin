<?php
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . '../../includes/LVYID_Options.php';
require_once plugin_dir_path(__FILE__) . '../../app/LVYID_Logger.php';

class LVYID_YandexLogin
{

    use LVYID_Options;

    private $login_url = 'https://login.yandex.ru/info?format=json';

    private $access_token_url = 'https://oauth.yandex.ru/token';

    private $options;

    private $log_class;

    public function __construct()
    {
        $options = LVYID_Options::getOptions();
        $this->options = $options ?? null;
        $this->log_class = new LVYID_Logger();
    }


    public function getAccessToken($code)
    {
        $options = $this->options;

        $grant_type = 'authorization_code';

        if (empty($options['client_id']) || empty($options['client_secret'])) {
            $this->log_class->error('not set client_secret or client_id');
            return ['status' => false, 'error' => 'Не указан client_id или client_secret в настройках плагина'];
        }

        $url = $this->access_token_url;

        $args = [
            'body' => [
                'grant_type' => $grant_type,
                'code' => $code,
                'client_id' => $options['client_id'],
                'client_secret' => $options['client_secret']
            ],
            'timeout' => 15,
            'blocking' => true,
            'headers' => [],
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $this->log_class->error('getAccessToken WP_Error: ' . $response->get_error_message());
            return ['status' => false, 'error' => $response->get_error_message()];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $this->log_class->error('getAccessToken error (' . $response_code . '): ' . $response_body);
            $decoded = json_decode($response_body, true);
            $err_msg = $decoded['error_description'] ?? $decoded['error'] ?? 'Ошибка получения токена (код ответа ' . $response_code . ')';
            return ['status' => false, 'error' => $err_msg];
        }

        $decoded = json_decode($response_body, true);
        if (empty($decoded['access_token'])) {
            return ['status' => false, 'error' => 'Не удалось получить access_token из ответа Яндекс OAuth'];
        }

        return ['status' => true, 'access_token' => $decoded['access_token']];
    }

    /**
     * Запрашиваем данные пользователя
     *
     */
    public function getInfo($oauth_token)
    {
        $args = [
            'headers' => [
                'Authorization' => 'oAuth ' . $oauth_token,
            ],
            'timeout' => 15,
        ];

        $response = wp_remote_get($this->login_url, $args);

        if (is_wp_error($response)) {
            $this->log_class->error('getInfo WP_Error: ' . $response->get_error_message());
            throw new Exception('Ошибка: ' . sprintf('%s', esc_html($response->get_error_message())));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->log_class->error('getInfo HTTP error: ' . $response_code);
            return null;
        }

        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body);

        if (!empty($result->default_email)) {
            // Проверка, что токен выдан именно нашему приложению
            if (!empty($this->options['client_id'])) {
                if (empty($result->client_id) || !hash_equals((string) $this->options['client_id'], (string) $result->client_id)) {
                    $this->log_class->error('getInfo security check failed: client_id mismatch');
                    return null;
                }
            }

            return $result;
        }

        return null;
    }
}
