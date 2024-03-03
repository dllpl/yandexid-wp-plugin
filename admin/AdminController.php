<?php

require_once plugin_dir_path(__FILE__) . '../includes/Options.php';

class AdminController
{
    use Options;

    private $options;

    public function __construct()
    {
        $options = Options::getOptions();
        $this->options = $options ?? null;
    }

    public function addMenu()
    {
        add_options_page('Интеграция с Яндекс ID', 'Интеграция с Яндекс ID', 'manage_options', 'yandexid_settings', [$this, 'settingsPage']);
    }

    public function settingsPage()
    {
        $options = $this->options;
        ?>
        <style>
            label {font-size: 16px}
        </style>
        <div class="wrap">
            <h2><?php echo get_admin_page_title() ?></h2>
            <form>
                <h3>Данные приложения Яндекс ID и настройки плагина</h3>
                <label for="сlient_id">ClientID<span style="color: red">*</span></label> <br>
                <input type="text" id="сlient_id" name="сlient_id" value="<?php echo $options['сlient_id'] ?? null ?>"
                       required maxlength="32" minlength="32"><br>
                <label for="client_secret">Client secret<span style="color: red">*</span></label> <br>
                <input type="password" id="client_secret" name="client_secret"
                       value="<?php echo $options['client_secret'] ?? null ?>"
                       required maxlength="32" minlength="32"><br>
                <hr>
                <div style="display: flex;">
                    <div style="display: flex; align-items: end">
                        <label for="widget">Показать виджет</label>
                        <input type="checkbox" id="widget" name="widget" value="<?php echo $options['widget'] ?? null ?>" style="margin-left: 5px">
                    </div>
                    <div style="display: flex; align-items: end; margin-left: 20px">
                        <label for="button">Показать кнопку</label>
                        <input type="checkbox" id="button" name="button" value="<?php echo $options['button'] ?? null ?>" style="margin-left: 5px">
                    </div>
                </div>



                <div style="color: red" id="error"></div>
                <div style="color: forestgreen" id="success"></div>
                <?php
                submit_button();
                ?>
            </form>
            <script>
                const form = document.querySelector('form');
                const url = '/wp-json/yandexid_webseed/updateSettings';

                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const formData = new FormData(form);
                    const data = {};
                    for (const [key, value] of formData.entries()) {
                        if (value === '') continue
                        data[key] = value;
                    }
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-WP-Nonce': "<?php echo wp_create_nonce('wp_rest'); ?>",
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                document.getElementById('error').innerText = data?.message ?? data.data
                            } else {
                                document.getElementById('success').innerText = data.data
                            }
                        })
                        .catch(error => {
                            document.getElementById('error').innerText = error.data.message
                        })
                        .finally(() => {
                            setTimeout(() => {
                                document.getElementById('error').innerText = ''
                                document.getElementById('success').innerText = ''
                            }, 5000)
                        })
                });
            </script>
            <style>
                input {
                    width: 400px;
                }
            </style>
        </div>
        <?php


    }

    public static function updateSettings(WP_REST_Request $request)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'yandexid_webseed_options';

        $data = [
            'сlient_id' => trim($request['сlient_id']),
            'client_secret' => trim($request['client_secret']),
            'button' => $request['button'],
            'widget' => $request['widget'],
        ];

        $result = $wpdb->insert($table_name, $data);

        if ($result) {
            return wp_send_json_success('Успешное сохранение данных');
        } else {
            return wp_send_json_error('Ошибка при сохранении данных');
        }
    }
}