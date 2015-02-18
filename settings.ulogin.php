<?php

/*
 * uLogin Settings class
 */

if (!class_exists("uLoginPluginSettings")) {
    class uLoginPluginSettings{
        static private $_uLoginOptionsName = 'ulogin_plugin_options';
        static private $_uLoginOldOptionsName = 'uLoginPluginOptions';
        static private $_uLoginOptions = array(
                                        'label' => 'Войти с помощью:',
                                        'set_url' => true,
                                        'uloginID1' => '',
                                        'uloginID2' => '',
                                        'uloginID3' => '',
                                        'mail'      => true,
                                       );

        static private $_uLoginDefaultOptions = array(
                                        'display' => 'small',
                                        'providers' => 'vkontakte,odnoklassniki,mailru,facebook',
                                        'hidden' => 'other',
                                        'fields' => 'first_name,last_name,email,photo,photo_big',
                                        'optional' => 'phone',
                                        'redirect_uri' => '',
                                        'label' => 'Войти с помощью:',
                                    );


        static private $count = 0;

        function __construct(){
        }

        /**
         * Получение данных о настройках плагина
         */
        static function getOptions(){
            $uLoginOptions = get_option(self::$_uLoginOptionsName);
            $update_fl = false;
            if (!empty($uLoginOptions) && is_array($uLoginOptions)) {
                foreach ($uLoginOptions as $key => $option){
                    self::$_uLoginOptions[$key] = $option;
                }
                foreach (self::$_uLoginOptions as $key => $option){
                    if ($uLoginOptions[$key] != $option){
                        $update_fl = true;
                        break;
                    }
                }
            }
            if ($update_fl){
                update_option(self::$_uLoginOptionsName, self::$_uLoginOptions);
            }
            return self::$_uLoginOptions;
        }

	    static function getOldOptions(){
		    $uLoginOldOptions = get_option(self::$_uLoginOldOptionsName);
		    if (!empty($uLoginOldOptions) && is_array($uLoginOldOptions)) {
			    foreach ($uLoginOldOptions as $key => $option){
				    self::$_uLoginDefaultOptions[$key] = $option;
			    }
		    }
		    return self::$_uLoginDefaultOptions;
	    }

        static function getDefaultOptionsArray(){
            $uLoginDefaultOptions = self::getOldOptions();
	        if (self::$_uLoginOptions['label'] != 'Войти с помощью:')
                $uLoginDefaultOptions['label'] = self::$_uLoginOptions['label'];
            return $uLoginDefaultOptions;
        }

        static function set_ulogin_table(){
            global $wpdb;
            return $wpdb->prefix . "ulogin";
        }


	    static function register_ulogin() {
		    self::register_database_table();
		    update_option("avatar_default", 'ulogin');
	    }

        /**
         *  Создание/обновление таблицы "ulogin" в БД
         */
        static function register_database_table() {
            global $wpdb, $charset_collate;

            $ulogin_db_version = "1.2";
            $installed_ulogin_db_version = get_option( "ulogin_db_version" );

            $ulogin_table = self::set_ulogin_table();
            if(($wpdb->get_var("SHOW TABLES LIKE '$ulogin_table'") != $ulogin_table)
                || ($installed_ulogin_db_version != $ulogin_db_version)) {

                $sql = "CREATE TABLE $ulogin_table (
                   ID bigint(20) unsigned NOT NULL auto_increment,
                   userid bigint(20) unsigned NOT NULL,
                   identity varchar(250) NOT NULL,
                   network varchar(20),
                   PRIMARY KEY  (ID),
                   UNIQUE KEY identity (identity)
                   ) $charset_collate;";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);

                if (isset($installed_ulogin_db_version)){
                    update_option( "ulogin_db_version", $ulogin_db_version );
                } else {
                    add_option("ulogin_db_version", $ulogin_db_version);
                }
            }
            if (empty($wpdb->ulogin)){
                $wpdb->ulogin = $ulogin_table;
            }
        }

        /**
         * Формирование страницы в админке
         */
        function print_admin_page() {
            //Проверка прав доступа пользователя
            if (!current_user_can('manage_options'))
            {
                wp_die( __('You do not have sufficient permissions to access this page.'),'',array('back_link' => true, 'response' => 403));
            }

            $uLoginOptions = self::getOptions();
            if (isset($_POST['update_uLoginPluginSettings'])) {
                if (isset($_POST['uloginLabel'])) {
                    $uLoginOptions['label'] = $_POST['uloginLabel'];
                }

                if (isset($_POST['uloginSetUrl'])) {
                    $uLoginOptions['set_url'] = true;
                } else {
                    $uLoginOptions['set_url'] = false;
                }

                if (isset($_POST['uloginID1'])) {
                    $uLoginOptions['uloginID1'] = $_POST['uloginID1'];
                }

                if (isset($_POST['uloginID2'])) {
                    $uLoginOptions['uloginID2'] = $_POST['uloginID2'];
                }

                if (isset($_POST['uloginID3'])) {
                    $uLoginOptions['uloginID3'] = $_POST['uloginID3'];
                }

                update_option(self::$_uLoginOptionsName, $uLoginOptions);
            }

            $form = file_get_contents('templates/settings.form.html',true);
            $form = str_replace('{URI}', $_SERVER['REQUEST_URI'], $form);
            $form = str_replace('{LABEL}', $uLoginOptions['label'], $form);
            $form = str_replace('{ULOGINID1}', $uLoginOptions['uloginID1'], $form);
            $form = str_replace('{ULOGINID2}', $uLoginOptions['uloginID2'], $form);
            $form = str_replace('{ULOGINID3}', $uLoginOptions['uloginID3'], $form);
            $form = str_replace('{SETURL_CHECKED}', $uLoginOptions['set_url'] ? 'checked="checked"' : '', $form);

            //Текстовые поля страницы для перевода
            $form = str_replace('{HEADER_TXT}', __('Настройки плагина <b>uLogin</b>'), $form);
            $form = str_replace('{LABEL_LABEL_TXT}', __('Текст'), $form);
            $form = str_replace('{SPAN_TXT}', __('Данные обновлены'), $form);
            $form = str_replace('{BUTTON_VALUE}', __('Применить'), $form);


            $form = str_replace('{H3_1_TXT}', __('ID uLogin\'а из <a href="http://ulogin.ru/lk.php" target="_blank">Личного кабинета</a>'), $form);
            $form = str_replace('{H3_3_TXT}', __('Другие параметры'), $form);
            $ulogin_id_label = __('uLogin ID');
            $ulogin_label[] = $ulogin_id_label . __(' форма входа');
            $ulogin_label[] = $ulogin_id_label . __(' форма для комментариев');
            $ulogin_label[] = $ulogin_id_label . __(' форма для профиля пользователя');
            $form = str_replace('{LABEL_ULOGINID1_TXT}', $ulogin_label[0], $form);
            $form = str_replace('{LABEL_ULOGINID2_TXT}', $ulogin_label[1], $form);
            $form = str_replace('{LABEL_ULOGINID3_TXT}', $ulogin_label[2], $form);

            $form = str_replace('{LABEL_SETURL_TXT}', 'Сохранять ссылку на профиль', $form);

            $form = str_replace('{ULOGINID1_DESCR}', 'Идентификатор виджета в окне входа и регистрации. Пустое поле - виджет по умолчанию', $form);
            $form = str_replace('{ULOGINID2_DESCR}', 'Идентификатор виджета для комментариев. Пустое поле - виджет по умолчанию', $form);
            $form = str_replace('{ULOGINID3_DESCR}', 'Идентификатор виджета для профиля пользователя. Пустое поле - виджет по умолчанию', $form);
            $form = str_replace('{LABEL_DESCR}',     'Текст типа "Войти с помощью:"', $form);
            $form = str_replace('{SETURL_DESCR}',    'Сохранять ссылку на страницу пользователя в соцсети при авторизации через uLogin', $form);

            echo $form;
        }

        /**
         * Получает строку js.
         */
        function get_js_str(){
            $js_string = '<script src="//ulogin.ru/js/ulogin.js" type="text/javascript"></script>';
            if (self::$count == 0){
                self::$count++;
                return $js_string;
            } else {
                self::$count++;
                return '';
            }
        }

        /**
         * Вызов функции uLogin.customInit() для ускоренной инициализации
         * @param $ulogin_id
         * @return string
         */
        function get_custom_init_str($ulogin_id){
            $string_id = '';
            if (is_array($ulogin_id)){
                foreach ($ulogin_id as $id){
                    $string_id.= '\''.$id.'\''.',';
                }
                $string_id = substr($string_id, 0, -1);
            } else {
                $string_id = '\''.$ulogin_id.'\'';
            }
            return '<script>uLogin.customInit('.$string_id.')</script>';
        }

        /*
         * Получает div панель
         */
        function get_div_panel($place = 0, $with_label = true, $id = '', $div_only = false){
            $ulOptions = self::getOptions();
	        $default_panel = false;

	        switch($place){
		        case 0:
			        $uloginID = $ulOptions['uloginID1'];
			        break;
		        case 1:
			        $uloginID = $ulOptions['uloginID2'];
			        break;
                case 2:
                    $uloginID = $ulOptions['uloginID3'];
                    if (empty($uloginID)) {
                        $uloginID = $ulOptions['uloginID2'];
                    }
                    break;
		        default:
			        $uloginID = $ulOptions['uloginID1'];
	        }

	        if (empty($uloginID)){
		        $ulOptions = self::getOldOptions();
		        $default_panel = true;
	        }

            $id = ($id=='' ? 'uLogin'.self::$count : $id);
            $panel = $with_label ? '<div class="ulogin_label">'.$ulOptions['label'].'&nbsp;</div>' : '';
            $redirect_uri = urlencode(home_url().'/?ulogin=token&backurl='.urlencode(ulogin_get_current_page_url().($place===1?'#commentform':'')));

	        $panel .= '<div id='.$id.' class="ulogin_panel"';

            if ($default_panel){
                $ulOptions['redirect_uri'] = $redirect_uri;
	            unset($ulOptions['label']);
	            $x_ulogin_params = '';
                foreach ($ulOptions as $key=>$value){
                    $x_ulogin_params.= $key.'='.$value.';';
                }
	            if ($ulOptions['display'] != 'window') {
		            $panel .= ' data-ulogin="' . $x_ulogin_params . '"></div>';
	            } else {
		            $panel .= ' data-ulogin="' . $x_ulogin_params . '" href="#"><img src="https://ulogin.ru/img/button.png" width=187 height=30 alt="МультиВход"/></div>';
	            }
            } else {
                $panel .= ' data-uloginid="'.$uloginID.'" data-ulogin="redirect_uri='.$redirect_uri.'"></div>';
            }

            $panel = '<div class="ulogin_block">'.$panel.'<div style="clear:both"></div></div>';
            if (!$div_only){
                return $this->get_js_str().$panel.$this->get_custom_init_str($id);
            } else return $panel;
        }

        function uLogin_Plugin_add_help_tab(){
            get_current_screen()->add_help_tab( array(
                'id'      => 'overview',
                'title'   => __('Overview'),
                'content' => '<p>' . __('<a href="http://ulogin.ru" target="_blank">uLogin</a> — это инструмент, который позволяет пользователям получить единый доступ к различным Интернет-сервисам без необходимости повторной регистрации, ' .
                        'а владельцам сайтов — получить дополнительный приток клиентов из социальных сетей и популярных порталов (Google, Яндекс, Mail.ru, ВКонтакте, Facebook и др.)') . '</p>',
            ) );

            get_current_screen()->add_help_tab( array(
                'id'      => 'ulogin-common-settings',
                'title'   => __('Настройки в ЛК'),
                'content' => '<p>' . __('Чтобы создать свой виджет для входа на сайт достаточно зайти в <a href="http://ulogin.ru/lk.php" target="_blank">Личный Кабинет (ЛК)</a> на сайте <a href="http://ulogin.ru" target="_blank">uLogin.ru</a> и на вкладке "Виджеты" добавить новый виджет. ' .
                        'Вы можете редактировать свой виджет самостоятельно.') . '</p>' .
                        '<p>' . __('Для успешной работы плагина необходимо ' .
                        'включить в обязательных полях профиля поле <b>Еmail</b> в <a href="http://ulogin.ru/lk.php" target="_blank">Личном кабинете uLogin</a>') . '</p>',
            ) );

            get_current_screen()->add_help_tab( array(
                'id'      => 'common-settings',
                'title'   => __('Common Settings'),
                'content' => '<p>' . __('В данном плагине поддерживаются до двух различных виджетов uLogin.') . '</p>' .
                    '<p>' . __('Для работы плагина с настройками из ЛК достаточно указать ID виджета в полях') . ' <b>' . __('uLogin\'s ID') .'</b>.' . '</p>' .
                    '<p>' .'<b>'. __('Label text').'</b >' . __(' - текст перед отображением виджета.') . '</p>',
            ) );

            get_current_screen()->set_help_sidebar(
                '<p><strong>' . __('For more information:') . '</strong></p>' .
                '<p>' . __('<a href="http://ulogin.ru" target="_blank">Страница uLogin</a>') . '</p>' .
                '<p>' . __('<a href="http://ulogin.ru/features.php" target="_blank">О проекте</a>') . '</p>' .
                '<p>' . __('<a href="http://ulogin.ru/help.php#cms-wp" target="_blank">Как настроить uLogin</a>') . '</p>'
            );
        }

    }
}
