<?php
/*
Plugin Name: uLogin - виджет авторизации через социальные сети
Plugin URI: http://ulogin.ru/
Description: uLogin — это инструмент, который позволяет пользователям получить единый доступ к различным Интернет-сервисам без необходимости повторной регистрации, а владельцам сайтов — получить дополнительный приток клиентов из социальных сетей и популярных порталов (Google, Яндекс, Mail.ru, ВКонтакте, Facebook и др.)
Version: 2.0.0
Author: uLogin
Author URI: http://ulogin.ru/
License: GPL2
*/

require_once('settings.ulogin.php');

global $current_user;

/*
 * Создание хуков
 */
register_activation_hook(__FILE__, array( 'uLoginPluginSettings', 'register_database_table'));

add_action('init', 'ulogin_init');

add_action('admin_menu', 'uLoginSettingsPage');

add_action('comment_form_must_log_in_after','ulogin_comment_form_before_fields');
if (!add_action('comment_form_before_fields','ulogin_comment_form_before_fields')){
    add_action('comment_form', 'ulogin_comment_form');
}
add_action('login_form', 'ulogin_form_panel');
add_action('register_form','ulogin_form_panel');

add_action('profile_personal_options', 'ulogin_profile_personal_options');

add_action('personal_options_update', 'ulogin_personal_options_update');
add_action('edit_user_profile_update', 'ulogin_personal_options_update');


add_filter('get_avatar', 'ulogin_get_avatar', 10, 2);

add_filter('request', 'ulogin_request');

/**
 *  Ссылка для редиректа должна оканчиваться "?ulogin=token"
 */
add_filter('query_vars','ulogin_query_vars');
function ulogin_query_vars($query_vars){
    $query_vars[] = 'ulogin';
    return $query_vars;
}
function ulogin_request($query_vars){
    if ($query_vars['ulogin'] == 'token'){
        add_action('parse_request', 'ulogin_parse_request');
    }
	if ($query_vars['ulogin'] == 'deleteaccount'){
		add_action('parse_request', 'ulogin_deleteaccount_request');
	}
    return $query_vars;
}

/**
 * Для поддержки плагина Simplemodal Login Form
 */
add_filter('simplemodal_login_form', 'ulogin_simplemodal_login_form');
function ulogin_simplemodal_login_form($text) {
    return str_replace('<div class="simplemodal-login-fields">', '<div class="simplemodal-login-fields">' . get_ulogin_panel('uLoginSMLF'), $text);
}

/**
 * Сохранение текущей страницы в cookie
 */
function ulogin_init (){
	if (stripos($_SERVER["QUERY_STRING"], 'ulogin=token') === false
	    && stripos($_SERVER["QUERY_STRING"], 'ulogin=deleteaccount') === false
	    && !(defined('DOING_AJAX') && DOING_AJAX)) {
		$current_page_url = get_current_page_url();
		$current_page_url = preg_replace('/(&|\?)reauth=1$/', '', $current_page_url);
		$current_page_url = preg_replace('/(&|\?)reauth=1&/', '$1', $current_page_url);
		setcookie( 'ulogin_current_page', $current_page_url, null, '/' );
	}
}

/**
 * Добавление страницы настроек
 */
function uLoginSettingsPage() {
    $ulPluginSettings = new uLoginPluginSettings();
    if (!isset($ulPluginSettings)) {
        wp_die( __('Plugin uLogin has been installed incorrectly.') );
        return;
    }
    if(function_exists('add_plugins_page')){
        $uLogin_Plugin_Settings = add_plugins_page('uLogin Plugin Settings', 'uLogin', 'manage_options', basename(__FILE__), array(&$ulPluginSettings, 'print_admin_page'));
        add_action('load-'.$uLogin_Plugin_Settings, array(&$ulPluginSettings, 'uLogin_Plugin_add_help_tab'));
    }
}	


/**
 * --Возвращает код JavaScript-функции, устанавливающей параметры uLogin
 */
function ulogin_js_setparams() {
    $ulOptions = uLoginPluginSettings::getDefaultOptionsArray();
    if(is_array($ulOptions)) {
            $x_ulogin_params = '';
            foreach ($ulOptions as $key=>$value){
                if ($key != 'label') {
                    $x_ulogin_params.= $key.'='.$value.';';
                }
            }
            return 	'<script type=text/javascript>ulogin_addr=function(id,comment) {'.
			'document.getElementById(id).setAttribute("x-ulogin-params","'.$x_ulogin_params.'redirect_uri="+encodeURIComponent((location.href.indexOf(\'#\') != -1 ? location.href.substr(0, location.href.indexOf(\'#\')) : location.href) + \'&ulogin=token\' + (comment?\'#commentform\':\'\')));'.
			'}</script>';
	}
	return '';
}

/**
 * --Возвращает код div-а с кнопками uLogin
 */
function ulogin_div($id) {
    $ulOptions = uLoginPluginSettings::getDefaultOptionsArray();
    $panel = '';
    if (is_array($ulOptions)){
        $panel = '<div style="float:left;line-height:24px">'.$ulOptions['label'].'&nbsp;</div><div id='.$id.' data-ulogin="display=panel;fields=first_name,last_name;providers=vkontakte,odnoklassniki,mailru,facebook;hidden=other;></div><div style="clear:both"></div>';
    }
    return $panel ;
}

/**
 * --Возвращает код uLogin для формы добавления комментариев
 * для версии WP раннее 3.0.0
 */
function ulogin_comment_form() {
    global $current_user;
    if ($current_user->ID == 0) {
        echo '<script src="//ulogin.ru/js/ulogin.js" type="text/javascript"></script>'.
            ulogin_js_setparams().
            '<script type="text/javascript">'.
                '(function() {'.
                    'var form = document.getElementById(\'commentform\');'.
                    'if (form) {'.
                        'var div = document.createElement(\'div\');'.
                        'div.innerHTML = \''.ulogin_div('uLogin').'\';'.
                        'form.parentNode.insertBefore(div, form);'.
                        'ulogin_addr("uLogin",1);'.
                    '}'.
                '})();'.
            '</script>';

	}
}

/**
 * !-Возвращает код uLogin для формы добавления комментариев
 */
function ulogin_comment_form_before_fields(){
    echo get_ulogin_panel(1);
}

/**
 * Возвращает код uLogin для формы входа и регистрации
 */
function ulogin_form_panel(){
    echo get_ulogin_panel(0);
}

/**
 * Возвращает код uLogin для отображения в произвольном месте
 * @param int $panel - номер uLogin панели, соответствует указанным в настройках плагина полям с ID (значение 0 - для первого плагина, 1 - для второго)
 * @param bool $with_label - указывает, стоит ли отображать строку типа "Войти с помощью:" рядом с виджетом (true - строка отображается)
 * @param bool $is_logining - указывает, отображать ли виджет, если пользователь залогинен (false - виджет скрывается)
 * @param string $id - id для div-панели
 * @return string - код uLogin для отображения в произвольном месте
 */
function get_ulogin_panel($panel = 0, $with_label = true, $is_logining = false, $id='') {
	global $current_user;
	if (!$current_user->ID || $is_logining) {
        $ulPluginSettings = new uLoginPluginSettings();
        return $ulPluginSettings->get_div_panel($panel, $with_label, $id);
	}
	return '';
}

/**
 * Возвращает код uLogin для отображения в произвольном месте
 * @param string $id - id для div-панели
 * @return string - код uLogin для отображения в произвольном месте
 */
function ulogin_panel($id='') {
    return get_ulogin_panel(0, false, false, $id);
}

/**
 * "Обменивает" токен на пользовательские данные
 */
function get_user_from_token($token = false)
{
    $response = false;
    if ($token){
        $request = 'http://ulogin.ru/token.php?token=' . $token . '&host=' . $_SERVER['HTTP_HOST'];
        if(in_array('curl', get_loaded_extensions())){
            $c = curl_init($request);
            curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($c);
            curl_close($c);

        }elseif (function_exists('file_get_contents') && ini_get('allow_url_fopen')){
            $response = file_get_contents($request);
        }
    }
    return $response;
}

/**
 * Проверка пользовательских данных, полученных по токену
 * @param $u_user - пользовательские данные
 * @return bool
 */
function check_token_error($u_user){
    if (!is_array($u_user)){
        wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" .
            "Данные о пользователе содержат неверный формат."), 'uLogin error', array('back_link' => true));
        return false;
    }

    if (isset($u_user['error'])){
        $strpos = strpos($u_user['error'],'host is not');
        if ($strpos){
            wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" .
                    "<i>ERROR</i>: адрес хоста не совпадает с оригиналом") . sub($u_user['error'],intval($strpos)+12), 'uLogin error', array('back_link' => true));
        }
        switch ($u_user['error']){
            case 'token expired':
                wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" .
                    "<i>ERROR</i>: время жизни токена истекло"), 'uLogin error', array('back_link' => true));
                break;
            case 'invalid token':
                wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" .
                    "<i>ERROR</i>: неверный токен"), 'uLogin error', array('back_link' => true));
                break;
            default:
                wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" .
                        "<i>ERROR</i>: ") . $u_user['error'], 'uLogin error', array('back_link' => true));
        }
        return false;
    }
    if (!isset($u_user['identity'])){
        wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" .
            "В возвращаемых данных отсутствует переменная <b>identity</b>."), 'uLogin error', array('back_link' => true));
        return false;
    }
    return true;
}

/**
 * @param $user_id
 * @return bool
 */
function check_user_id($user_id){
    global $current_user;
    if (($current_user->ID > 0) && ($user_id > 0) && ($current_user->ID != $user_id)){
        wp_die(__("Данный аккаунт привязан к другому пользователю.</br>" .
            " Вы не можете использовать этот аккаунт"), 'uLogin warning', array('back_link' => true));
        return false;
    }
    return true;
}

/**
 * Обработка ответа сервера авторизации
 */
function ulogin_parse_request() {
    if (!isset($_POST['token'])) {
        wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" .
            "Не был получен токен uLogin."), 'uLogin error', array('back_link' => true));
        return;  // не был получен токен uLogin
    }

	$s = get_user_from_token($_POST['token']);

    if (!$s){
        wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" .
            "Не удалось получить данные о пользователе с помощью токена."), 'uLogin error', array('back_link' => true));
        return;
    }

	$u_user = json_decode($s, true);

    if (!check_token_error($u_user)){
        return;
    }

    global $wpdb;
    uLoginPluginSettings::register_database_table();

    $user_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT userid FROM $wpdb->ulogin where identity = %s", urlencode($u_user['identity'])
                    )
                );

    if (isset($user_id)){
        $wp_user = get_userdata($user_id);
        if ($wp_user->ID > 0 && $user_id > 0){
            check_user_id($user_id);
            // пользователь зарегистрирован. Необходимо выполнить вход и обновить данные (если необходимо).
            enter_user($u_user, $user_id);
        } else {
            // данные о пользователе есть в ulogin_table, но отсутствуют в WP. Необходимо выполнить перерегистрацию в ulogin_table и регистрацию/вход в WP.
            $user_id = registration_ulogin_user($u_user, 1);
        }
    } else {
        // пользователь НЕ обнаружен в ulogin_table. Необходимо выполнить регистрацию в ulogin_table и регистрацию/вход в WP.
        $user_id = registration_ulogin_user($u_user);
    }

    // обновление данных и Вход
    if ($user_id > 0){
        enter_user($u_user, $user_id);
    }
}

/**
 * Обновление данных о пользователе и вход
 * @param $u_user - данные о пользователе, полученные от uLogin
 * @param $user_id - идентификатор пользователя
 */
function enter_user($u_user, $user_id){
    $updating_data = array(
        'user_url'        => $u_user['profile'],
        'user_email'      => $u_user['email'],
        'first_name'      => $u_user['first_name'],
        'last_name'       => $u_user['last_name'],
        'display_name'    => $u_user['first_name'] . ' ' . $u_user['last_name']);
    $update_user_data = array('ID' => $user_id);
    $wp_user = get_userdata($user_id);

    foreach ($updating_data as $datum => $value){
        if (isset($value) && empty($wp_user->{$datum})){
            $update_user_data[$datum] = $value;
        }
    }

    wp_update_user($update_user_data);

    $file_url = $u_user['photo'];
    $q = true;

    //directory to import to
    $avatar_dir = str_replace('\\','/',dirname(dirname(dirname(dirname(__FILE__))))).'/wp-content/uploads/';
    if($q && !file_exists($avatar_dir)) {
        $q = mkdir($avatar_dir);
    }
    $avatar_dir .= 'ulogin_avatars/';
    if($q && !file_exists($avatar_dir)) {
        $q = mkdir($avatar_dir);
    }

    if ($q && @fclose(@fopen($file_url, "r"))) { //make sure the file actually exists
        $filename = $u_user['network'] . '_' . $u_user['uid'];
        $q = copy($file_url, $avatar_dir.$filename);

        $res = '';
        $size = getimagesize($avatar_dir.$filename);
        switch ($size[2]) {
            case IMAGETYPE_GIF:
                $res = '.gif';
                break;
            case IMAGETYPE_JPEG:
                $res = '.jpg';
                break;
            case IMAGETYPE_PNG:
                $res = '.png';
                break;
            case IMAGETYPE_BMP:
                $res = '.bmp';
                break;
        }

        if ($q && $res) {
            if ($q = rename($avatar_dir.$filename, $avatar_dir.$filename.$res)) $filename = $filename.$res;
        }

        if ($q) {
            if (function_exists('update_user_meta')) {
                update_user_meta($user_id, 'ulogin_photo', home_url() . '/wp-content/uploads/ulogin_avatars/' . $filename);
            } else {
                update_usermeta($user_id, 'ulogin_photo', home_url() . '/wp-content/uploads/ulogin_avatars/' . $filename);
            }
        }
    }

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);

    $login_page = $_COOKIE['ulogin_current_page'];

    if (!isset($login_page) || (substr_count(urlencode($login_page), urlencode(wp_login_url())) > 0)){
        wp_redirect(home_url());
    } else {
        wp_redirect($login_page);
    }
    exit;
}

/**
 * Регистрация на сайте и в таблице uLogin
 * @param $u_user - данные о пользователе, полученные от uLogin
 * @param int $in_db - при значении 1 необходимо переписать данные в таблице uLogin
 * @return bool|int|WP_Error
 */
function registration_ulogin_user($u_user, $in_db = 0){
    global $wpdb;

    if (!isset($u_user['email'])){
        wp_die(__("Через данную форму выполнить вход/регистрацию невозможно. </br>" .
            "Сообщиете администратору сайта о следующей ошибке: </br></br>" .
            "Необходимо указать <b>email</b> в возвращаемых полях <b>uLogin</b>"), 'uLogin warning', array('back_link' => true));
        return false;
    }

    $network = isset($u_user['network']) ? $u_user['network'] : '';

    // данные о пользователе есть в ulogin_table, но отсутствуют в WP
    if ($in_db == 1){
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->ulogin where identity = %s", urlencode($u_user['identity'])
            )
        );
    }

    $user_id = $wpdb->get_var(
                   $wpdb->prepare(
                       "SELECT ID FROM $wpdb->users where user_email = %s", $u_user['email']
                   )
               );
	// $check_m_user == true -> есть пользователь с таким email
	$check_m_user = $user_id > 0 ? true : false;

	global $current_user;
	// $isLoggedIn == true -> ползователь онлайн
	$isLoggedIn = $current_user->ID > 0 ? true : false;

    if (!$check_m_user && !$isLoggedIn){ // отсутствует пользователь с таким email в базе WP -> регистрация
        $user_login = generateNickname($u_user['first_name'],$u_user['last_name'],$u_user['nickname'],$u_user['bdate']);
        $user_pass = wp_generate_password();
        $user_id = wp_insert_user(array(
            'user_pass'       => $user_pass,
            'user_login'      => $user_login,
            'user_url'        => $u_user['profile'],
            'user_email'      => $u_user['email'],
            'first_name'      => $u_user['first_name'],
            'last_name'       => $u_user['last_name'],
            'display_name'    => $u_user['first_name'] . ' ' . $u_user['last_name']));

        $uLoginOptions = uLoginPluginSettings::getOptions();
        if (!is_wp_error($user_id) && $user_id > 0 && ($uLoginOptions['mail'] == 1)){
            wp_new_user_notification($user_id, $user_pass);
        }
        return insert_ulogin_row($user_id, $u_user['identity'], $network);

    } else { // существует пользователь с таким email или это текущий пользователь
        if (!isset($u_user["verified_email"]) || intval($u_user["verified_email"]) != 1){
	        wp_die('<script src="//ulogin.ru/js/ulogin.js"  type="text/javascript"></script><script type="text/javascript">uLogin.mergeAccounts("'.$_POST['token'].'")</script>' .
	                  __("Электронный адрес данного аккаунта совпадает с электронным адресом существующего пользователя. <br>Требуется подтверждение на владение указанным email.</br></br>"), __("Подтверждение аккаунта"), array('back_link' => true));
            return false;
        }
        if (intval($u_user["verified_email"]) == 1){
	        $user_id = $isLoggedIn ? $current_user->ID : $user_id;

            $other_u = $wpdb->get_col(
                           $wpdb->prepare(
                               "SELECT identity FROM $wpdb->ulogin where userid = %d", $user_id
                           )
                       );

            if ($other_u){
	            if(!$isLoggedIn && !isset($u_user['merge_account'])){
		            wp_die('<script src="//ulogin.ru/js/ulogin.js"  type="text/javascript"></script><script type="text/javascript">uLogin.mergeAccounts("'.$_POST['token'].'","'.$other_u[0].'")</script>' .
		                   __("С данным аккаунтом уже связаны данные из другой социальной сети. <br>Требуется привязка новой учётной записи социальной сети к этому аккаунту."), __("Синхронизация аккаунтов"), array('back_link' => true));
		            return false;
	            }

	            if (insert_ulogin_row($user_id, $u_user['identity'], $network) !== false){
		            ulogin_parse_request();
	            }
                return false;
            }
            return insert_ulogin_row($user_id, $u_user['identity'], $network);
        }
    }
    return false;
}

/**
 * Добавление новой привязки uLogin
 * в случае успешного выполнения возвращает $user_id иначе - wp_die с сообщением об ошибке
 */
function insert_ulogin_row($user_id, $identity, $network = ''){
    global $wpdb;
    if (!is_wp_error($user_id) && $user_id > 0){
        $err = $wpdb->insert(
                    $wpdb->ulogin,
                    array('userid' => $user_id, 'identity' => urlencode($identity), 'network' => $network),
                    array('%d', '%s', '%s')
                );
        if ($err !== false){

            return $user_id;
        }
    } elseif (is_wp_error($user_id)) {
        $err = $user_id;
    }
    if (is_wp_error($err)) {
        wp_die($err->get_error_message(), '', array('back_link' => true));
    }
    return false;
}

/**
 * Гнерация логина пользователя
 * в случае успешного выполнения возвращает уникальный логин пользователя
 */
function generateNickname($first_name, $last_name="", $nickname="", $bdate="", $delimiters=array('.', '_')) {
	$delim = array_shift($delimiters);

	$first_name = translitIt($first_name);
	$first_name_s = substr($first_name, 0, 1);

	$variants = array();
	if (!empty($nickname))
		$variants[] = $nickname;
	$variants[] = $first_name;
	if (!empty($last_name)) {
		$last_name = translitIt($last_name);
		$variants[] = $first_name.$delim.$last_name;
		$variants[] = $last_name.$delim.$first_name;
		$variants[] = $first_name_s.$delim.$last_name;
		$variants[] = $first_name_s.$last_name;
		$variants[] = $last_name.$delim.$first_name_s;
		$variants[] = $last_name.$first_name_s;
	}
	if (!empty($bdate)) {
		$date = explode('.', $bdate);
		$variants[] = $first_name.$date[2];
		$variants[] = $first_name.$delim.$date[2];
		$variants[] = $first_name.$date[0].$date[1];
		$variants[] = $first_name.$delim.$date[0].$date[1];
		$variants[] = $first_name.$delim.$last_name.$date[2];
		$variants[] = $first_name.$delim.$last_name.$delim.$date[2];
		$variants[] = $first_name.$delim.$last_name.$date[0].$date[1];
		$variants[] = $first_name.$delim.$last_name.$delim.$date[0].$date[1];
		$variants[] = $last_name.$delim.$first_name.$date[2];
		$variants[] = $last_name.$delim.$first_name.$delim.$date[2];
		$variants[] = $last_name.$delim.$first_name.$date[0].$date[1];
		$variants[] = $last_name.$delim.$first_name.$delim.$date[0].$date[1];
		$variants[] = $first_name_s.$delim.$last_name.$date[2];
		$variants[] = $first_name_s.$delim.$last_name.$delim.$date[2];
		$variants[] = $first_name_s.$delim.$last_name.$date[0].$date[1];
		$variants[] = $first_name_s.$delim.$last_name.$delim.$date[0].$date[1];
		$variants[] = $last_name.$delim.$first_name_s.$date[2];
		$variants[] = $last_name.$delim.$first_name_s.$delim.$date[2];
		$variants[] = $last_name.$delim.$first_name_s.$date[0].$date[1];
		$variants[] = $last_name.$delim.$first_name_s.$delim.$date[0].$date[1];
		$variants[] = $first_name_s.$last_name.$date[2];
		$variants[] = $first_name_s.$last_name.$delim.$date[2];
		$variants[] = $first_name_s.$last_name.$date[0].$date[1];
		$variants[] = $first_name_s.$last_name.$delim.$date[0].$date[1];
		$variants[] = $last_name.$first_name_s.$date[2];
		$variants[] = $last_name.$first_name_s.$delim.$date[2];
		$variants[] = $last_name.$first_name_s.$date[0].$date[1];
		$variants[] = $last_name.$first_name_s.$delim.$date[0].$date[1];
	}
	$i=0;

	$exist = true;
	while (true) {
		if ($exist = userExist($variants[$i])) {
			foreach ($delimiters as $del) {
				$replaced = str_replace($delim, $del, $variants[$i]);
				if($replaced !== $variants[$i]){
					$variants[$i] = $replaced;
					if (!$exist = userExist($variants[$i]))
						break;
				}
			}
		}
		if ($i >= count($variants) || !$exist)
			break;
		$i++;
	}

	if ($exist) {
		while ($exist) {
			$nickname = $first_name.mt_rand(1, 100000);
			$exist = userExist($nickname);
		}
		return $nickname;
	} else
		return $variants[$i];
}

/**
 * Проверка существует ли пользователь с заданным логином
 */
function userExist($login){
    if (get_user_by('login',$login) === false){
        return false;
    }
    return true;
}

/**
 * Транслит
 */
function translitIt($str) {
	$tr = array(
		"А"=>"a","Б"=>"b","В"=>"v","Г"=>"g",
		"Д"=>"d","Е"=>"e","Ж"=>"j","З"=>"z","И"=>"i",
		"Й"=>"y","К"=>"k","Л"=>"l","М"=>"m","Н"=>"n",
		"О"=>"o","П"=>"p","Р"=>"r","С"=>"s","Т"=>"t",
		"У"=>"u","Ф"=>"f","Х"=>"h","Ц"=>"ts","Ч"=>"ch",
		"Ш"=>"sh","Щ"=>"sch","Ъ"=>"","Ы"=>"yi","Ь"=>"",
		"Э"=>"e","Ю"=>"yu","Я"=>"ya","а"=>"a","б"=>"b",
		"в"=>"v","г"=>"g","д"=>"d","е"=>"e","ж"=>"j",
		"з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l",
		"м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
		"с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h",
		"ц"=>"ts","ч"=>"ch","ш"=>"sh","щ"=>"sch","ъ"=>"y",
		"ы"=>"y","ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya"
	);
	if (preg_match('/[^A-Za-z0-9\_\-]/', $str)) {
		$str = strtr($str,$tr);
		$str = preg_replace('/[^A-Za-z0-9\_\-\.]/', '', $str);
	}
	return $str;
}


/**
 * Возвращает текущий url
 */
function get_current_page_url() {
    $pageURL = 'http';
    if( isset($_SERVER["HTTPS"]) ) {
        if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
    }
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
    } else {
        $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
    }
    return $pageURL;
}

/*
 * Возвращает url аватарки пользователя
 */
function ulogin_get_avatar($avatar, $id_or_email) {
    if (is_numeric($id_or_email)) {
            $user_id = get_user_by('id', (int) $id_or_email)->ID;
    } elseif (is_object($id_or_email)) {
            if (!empty($id_or_email->user_id)) {
                    $user_id = $id_or_email->user_id;
            } elseif (!empty($id_or_email->comment_author_email)) {
                    $user_id = get_user_by('email', $id_or_email->comment_author_email)->ID;
            }
    } else {
            $user_id = get_user_by('email', $id_or_email)->ID;
    }
    $photo = get_user_meta($user_id, 'ulogin_photo', 1);
    if ($photo){
        return preg_replace('/src=([^\s]+)/i', 'src="' . $photo . '"', $avatar);
    }
    return $avatar;
}


function ulogin_deleteaccount_request () {
	if (!isset($_POST['delete_account']) || $_POST['delete_account'] != 'delete_account') {
		exit;
	}

	$user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';
	$network = isset($_POST['network']) ? $_POST['network'] : '';

	if ($user_id != '' && $network != '') {
		try {
			global $wpdb;
			uLoginPluginSettings::register_database_table();
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $wpdb->ulogin where userid = %d and network = '%s'", $user_id, $network
				)
			);

			echo json_encode(array(
				'title' => "Удаление аккаунта",
				'msg' => "Удаление аккаунта $network успешно выполнено",
				'user' => $user_id,
				'answerType' => 'ok'
			));
			exit;
		} catch (Exception $e) {
			echo json_encode(array(
				'title' => "Ошибка при удалении аккаунта",
				'msg' => "Exception: " . $e->getMessage(),
				'answerType' => 'error'
			));
			exit;
		}
	}
	exit;
}
/**
 * Удаление привязок к аккаунтам социальных сетей
 */
function ulogin_personal_options_update ($user_id){
    global $wpdb;
    uLoginPluginSettings::register_database_table();
    $networks = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT network FROM $wpdb->ulogin where userid = %d", $user_id
                    ), 0
                );
    if ($networks){
        foreach ($networks as $network){
            if (isset($_POST['ch_'.$network]))
            {
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM $wpdb->ulogin where userid = %d and network = '%s'", $user_id, $network
                    )
                );
            }
        }
    }
}

/**
 * Добавление формы синхронизации в профиль пользователя
 */
function ulogin_profile_personal_options ($user){
    global $wpdb;
    uLoginPluginSettings::register_database_table();

    $str_info['h3'] = __("Синхронизация аккаунтов");
    $str_info['str1'] = __("Синхронизация аккаунтов");
    $str_info['str2'] = __("Привязанные аккаунты");
    $str_info['about1'] = __("Привяжите ваши аккаунты соц. сетей к личному кабинету для быстрой авторизации через любой из них");
    $str_info['about2'] = __("Вы можете удалить привязку к аккаунту");

    $networks = $wpdb->get_col(
                    $wpdb->prepare(
                       "SELECT network FROM $wpdb->ulogin where userid = %d", $user->ID
                    ), 0
               );

    ?>
		<script type="text/javascript">
			if ( (typeof jQuery === 'undefined') && !window.jQuery ) {
				document.write(unescape("%3Cscript type='text/javascript' src='//ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js'%3E%3C/script%3E%3Cscript type='text/javascript'%3EjQuery.noConflict();%3C/script%3E"));
			} else {
				if((typeof jQuery === 'undefined') && window.jQuery) {
					jQuery = window.jQuery;
				} else if((typeof jQuery !== 'undefined') && !window.jQuery) {
					window.jQuery = jQuery;
				}
			}

			function uloginDeleteAccount(user_id, network){
				jQuery.ajax({
					url: '/?ulogin=deleteaccount',
					type: 'POST',
					dataType: 'json',
					cache: false,
					data: {
						delete_account: 'delete_account',
						user_id: user_id,
						network: network
					},
					error: function (data, textStatus, errorThrown) {
						alert('Не удалось выполнить запрос');
						console.log(textStatus);
						console.log(errorThrown);
						console.log(data);
					},
					success: function (data) {
						switch (data.answerType) {
							case 'error':
								alert(data.title + "\n" + data.msg);
								break;
							case 'ok':
								if (jQuery('#ulogin_accounts').find('#ulogin_'+network).length > 0){
									jQuery('#ulogin_accounts').find('#ulogin_'+network).hide();
								}
								break;
						}
					}
				});
			}
		</script>
		<style>
			.ulogin_network{
				height: 16px;
				padding: 4px 8px;
				width: auto;
				float: left;
				background-color: #d9e7b3;
				border-radius: 4px;
				border: 1px solid #c3d690;
				margin-right: 10px;
				margin-bottom: 10px;
				cursor: default;}
			.ulogin_network span.u_network{
				display: block;
				float: left;
				margin-left: 4px;
				line-height: 16px;
				font-size: 14px;}
			.ulogin_network span.del_network{
				display: block;
				float: left;
				margin-left: 4px;
				line-height: 10px;
				font-size: 10px;
				color: red;
				cursor: pointer;}
		</style>

        <div>
            <h3 title="<?php echo $str_info['about1'] ?>"><?php echo $str_info['h3'] ?></h3>
            <span class="description"><?php echo $str_info['about0'] ?></span>
            <table class="form-table">
                <tr>
                    <th><label><?php echo $str_info['str1'] ?></label></th>
                    <td> <?php echo get_ulogin_panel(1, false, true);?>
                        <span class="description"><?php echo $str_info['about1'] ?></span>
                </tr>
                <tr>
                    <th><label for="ulogin_accounts"><?php echo $str_info['str2'] ?></label></th>
                    <td><div id="ulogin_accounts" name="ulogin_accounts">
                            <?php
                                if ($networks){
                                    foreach ($networks as $network){
                                        echo "<div id='ulogin_".$network."' class='ulogin_network'>
                                                <span class='u_network'>$network</span>
                                                <span class='del_network'
                                                    title='Удалить привязку'
                                                    onclick=\"uloginDeleteAccount('$user->ID','$network')\">удалить</span>
                                              </div>";
                                    }
                                }
                            ?>
                        </div>
                        <br /><br /><span class="description"><?php echo $str_info['about2'] ?></span>
                </tr>
            </table>
        </div>
    <?php
}

