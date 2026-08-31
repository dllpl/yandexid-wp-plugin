<?php
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . '../Service/LVYID_YandexLogin.php';

class LVYID_UserController
{
    public function handler($access_token)
    {
        if (empty($access_token)) {
            return wp_send_json_error('Невозможно авторизовать пользователя.');
        }

        $yandexApi = new LVYID_YandexLogin();
        $user_data = $yandexApi->getInfo(sanitize_text_field($access_token));

        if (empty($user_data) || empty($user_data->default_email)) {
            return wp_send_json_error('Не удалось получить данные профиля Яндекс.');
        }

        $email = sanitize_email($user_data->default_email);
        $yandex_id = !empty($user_data->id) ? (string) $user_data->id : null;

        $user = null;

        // 1. Сначала ищем пользователя по сохраненному yandex_id
        if (!empty($yandex_id)) {
            $users = get_users([
                'meta_key'   => 'yandex_id',
                'meta_value' => $yandex_id,
                'number'     => 1,
                'fields'     => 'all',
            ]);
            if (!empty($users)) {
                $user = $users[0];
            }
        }

        // 2. Если по yandex_id не найден, ищем по email
        if (!$user && !empty($email)) {
            $user = get_user_by('email', $email);
            if ($user) {
                // Защита привилегированных аккаунтов от автоматической привязки
                if (user_can($user, 'manage_options') || in_array('administrator', (array) $user->roles, true) || in_array('editor', (array) $user->roles, true)) {
                    return wp_send_json_error('Вход через Яндекс для учетных записей с расширенными правами отключен в целях безопасности. Пожалуйста, используйте стандартную форму входа.');
                }
            }
        }

        $result = ['status' => true];

        if ($user) {
            wp_set_auth_cookie($user->ID);
            $this->yandexid_update_user($user->ID, $user_data);
        } else {
            $result = $this->yandexid_create_user($user_data);
        }

        if ($result['status']) {
            header('Content-Type: text/html; charset=UTF-8');
            echo "<script>window.opener.parent.location.reload();window.close();</script>";
            exit;
        } else {
            return wp_send_json_error($result['message']);
        }
    }

    private function yandexid_update_user($user_id, $user_data)
    {
        $wp_user = get_userdata($user_id);
        $userdata = ['ID' => $user_id];
        $needs_update = false;

        $first_name = $user_data->first_name ?? '';
        $last_name  = $user_data->last_name ?? '';

        // Заполняем имя и фамилию только если они еще не были заполнены пользователем
        if ($wp_user) {
            if (empty($wp_user->first_name) && !empty($first_name)) {
                $userdata['first_name'] = sanitize_text_field($first_name);
                $needs_update = true;
            }
            if (empty($wp_user->last_name) && !empty($last_name)) {
                $userdata['last_name'] = sanitize_text_field($last_name);
                $needs_update = true;
            }
            if (empty($wp_user->display_name)) {
                $fn = $userdata['first_name'] ?? $wp_user->first_name;
                $ln = $userdata['last_name'] ?? $wp_user->last_name;
                $disp = trim($fn . ' ' . $ln);
                $userdata['display_name'] = !empty($disp) ? $disp : ($user_data->display_name ?? $user_data->login ?? '');
                $needs_update = true;
            }
        }

        if ($needs_update) {
            wp_update_user($userdata);
        }

        $phone = $user_data->default_phone->number ?? null;

        $meta = [
            'yandex_phone'        => $phone,
            'yandex_birthday'     => $user_data->birthday ?? null,
            'yandex_gender'       => $user_data->sex ?? null,
            'yandex_login'        => $user_data->login ?? null,
            'yandex_id'           => $user_data->id ?? null,
            'yandex_real_name'    => $user_data->real_name ?? null,
            'yandex_display_name' => $user_data->display_name ?? null,
        ];

        if (isset($user_data->is_avatar_empty, $user_data->default_avatar_id) && !$user_data->is_avatar_empty && !empty($user_data->default_avatar_id)) {
            $meta['yandex_avatar'] = "https://avatars.yandex.net/get-yapic/{$user_data->default_avatar_id}/islands-200";
        }

        // Поля WooCommerce для оформления заказа (billing / shipping)
        if (!empty($phone) && empty(get_user_meta($user_id, 'billing_phone', true))) {
            $meta['billing_phone'] = sanitize_text_field($phone);
        }

        if (!empty($first_name) && empty(get_user_meta($user_id, 'billing_first_name', true))) {
            $meta['billing_first_name'] = sanitize_text_field($first_name);
        }

        if (!empty($last_name) && empty(get_user_meta($user_id, 'billing_last_name', true))) {
            $meta['billing_last_name'] = sanitize_text_field($last_name);
        }

        if (!empty($user_data->default_email) && empty(get_user_meta($user_id, 'billing_email', true))) {
            $meta['billing_email'] = sanitize_email($user_data->default_email);
        }

        if (!empty($first_name) && empty(get_user_meta($user_id, 'shipping_first_name', true))) {
            $meta['shipping_first_name'] = sanitize_text_field($first_name);
        }

        if (!empty($last_name) && empty(get_user_meta($user_id, 'shipping_last_name', true))) {
            $meta['shipping_last_name'] = sanitize_text_field($last_name);
        }

        foreach ($meta as $key => $value) {
            if (!is_null($value) && $value !== '') {
                update_user_meta($user_id, $key, $value);
            }
        }
    }

    private function yandexid_create_user($user_data)
    {
        $first_name = $user_data->first_name ?? '';
        $last_name  = $user_data->last_name ?? '';
        $email      = sanitize_email($user_data->default_email ?? '');
        $phone      = $user_data->default_phone->number ?? null;
        $display_name = trim($first_name . ' ' . $last_name);

        // Безопасный логин без утечки email в user_nicename/URL
        $yandex_id = !empty($user_data->id) ? (string) $user_data->id : '';
        $base_login = !empty($yandex_id) ? 'ya_' . sanitize_user($yandex_id, true) : 'ya_' . wp_generate_password(8, false, false);
        $user_login = $base_login;
        $i = 1;
        while (username_exists($user_login)) {
            $user_login = $base_login . '_' . $i;
            $i++;
        }

        $default_role = apply_filters('lvyid_new_user_role', get_option('default_role', 'subscriber'));

        $meta_input = [
            'yandex_phone'        => $phone,
            'yandex_birthday'     => $user_data->birthday ?? null,
            'yandex_gender'       => $user_data->sex ?? null,
            'yandex_login'        => $user_data->login ?? null,
            'yandex_id'           => $user_data->id ?? null,
            'yandex_real_name'    => $user_data->real_name ?? null,
            'yandex_display_name' => $user_data->display_name ?? null,
            'billing_first_name'  => $first_name,
            'billing_last_name'   => $last_name,
            'billing_email'       => $email,
            'shipping_first_name' => $first_name,
            'shipping_last_name'  => $last_name,
        ];

        if (!empty($phone)) {
            $meta_input['billing_phone'] = sanitize_text_field($phone);
        }

        if (isset($user_data->is_avatar_empty, $user_data->default_avatar_id) && !$user_data->is_avatar_empty && !empty($user_data->default_avatar_id)) {
            $meta_input['yandex_avatar'] = "https://avatars.yandex.net/get-yapic/{$user_data->default_avatar_id}/islands-200";
        }

        // Фильтруем пустые значения
        $meta_input = array_filter($meta_input, function ($val) {
            return !is_null($val) && $val !== '';
        });

        $userdata = [
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => !empty($display_name) ? $display_name : ($user_data->display_name ?? $user_data->login ?? $user_login),
            'user_login'   => $user_login,
            'user_pass'    => wp_generate_password(24, true, true),
            'user_email'   => $email,
            'role'         => $default_role,
            'meta_input'   => $meta_input,
        ];

        $user_id = wp_insert_user($userdata);

        if (!is_wp_error($user_id)) {
            wp_set_auth_cookie($user_id);
            wp_send_new_user_notifications($user_id);
            return ['status' => true];
        } else {
            return ['status' => false, 'message' => $user_id->get_error_message()];
        }
    }
}
