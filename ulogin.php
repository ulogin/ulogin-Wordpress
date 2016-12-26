<?php
/**
 * Plugin Name: uLogin - виджет авторизации через социальные сети
 * Plugin URI:  http://ulogin.ru/
 * Description: uLogin — это инструмент, который позволяет пользователям получить единый доступ к различным
 * Интернет-сервисам без необходимости повторной регистрации, а владельцам сайтов — получить дополнительный приток
 * клиентов из социальных сетей и популярных порталов (Google, Яндекс, Mail.ru, ВКонтакте, Facebook и др.)
 * Version:     2.6.0
 * Author:      uLogin
 * Author URI:  http://ulogin.ru/
 * License:     GNU General Public License, version 2
 */
require_once('settings.ulogin.php');
global $current_user;

$uLoginOptions = uLoginPluginSettings::getOptions();

/**
 * Создание хуков
 */
register_activation_hook(__FILE__, array('uLoginPluginSettings', 'register_ulogin'));
if(function_exists('register_uninstall_hook'))
	register_deactivation_hook(__FILE__, 'uninstall_ulogin');
add_action('admin_menu', 'uLoginSettingsPage');

if($uLoginOptions['comment_form_available']) {
	add_action('comment_form_must_log_in_after', 'ulogin_comment_form_before_fields');
	add_action('comment_form_top', 'ulogin_comment_form_before_fields');
}

if($uLoginOptions['login_form_available']) {
	add_action('login_form', 'ulogin_form_panel');
	add_action('register_form', 'ulogin_form_panel');
}

if($uLoginOptions['sync_form_available']) {
	add_action('profile_personal_options', 'ulogin_profile_personal_options');
}

add_action('delete_user', 'ulogin_delete_user');
add_filter('request', 'ulogin_request');
/**
 * Удаление следов плагина при его деактивации
 */
function uninstall_ulogin() {
}

/**
 *  Ссылка для редиректа должна оканчиваться "?ulogin=token"
 */
add_filter('query_vars', 'ulogin_query_vars');
function ulogin_query_vars($query_vars) {
	$query_vars[] = 'ulogin';

	return $query_vars;
}

function ulogin_request($query_vars) {
	if(isset($query_vars['ulogin'])) {
		if($query_vars['ulogin'] == 'token') {
			add_action('parse_request', 'ulogin_parse_request');
		}
		if($query_vars['ulogin'] == 'deleteaccount') {
			add_action('parse_request', 'ulogin_deleteaccount_request');
		}
	}

	return $query_vars;
}

/**
 * Регистрация css-файла
 */
function register_ulogin_styles() {
	wp_register_style('ulogin-style', plugins_url('ulogin/css/ulogin.css'));
	wp_register_style('ulogin-prov-style', '//ulogin.ru/css/providers.css');
}

add_action('init', 'register_ulogin_styles');
/**
 * Для поддержки Buddypress
 */
include_once(ABSPATH . 'wp-admin/includes/plugin.php');
if(is_plugin_active('buddypress/bp-loader.php')) {
	add_action('bp_after_profile_edit_content', 'ulogin_bp_custom_profile_edit_fields');
	add_filter('bp_core_fetch_avatar', 'ulogin_bp_core_fetch_avatar', 10, 2);
}
function ulogin_bp_core_fetch_avatar($html, $params) {
	$soc_avatar = uLoginPluginSettings::getOptions();
	$soc_avatar = $soc_avatar['social_avatar'];
	if($soc_avatar) {
		$photo = get_user_meta($params['item_id'], 'ulogin_photo', 1);
		if($photo && $params['object'] == 'user') {
			if(function_exists('bp_get_user_has_avatar')) {
				$photo = bp_get_user_has_avatar($params['item_id']) ? false : $photo;
			}
			if($photo) {
				$html = preg_replace('/src=".+?"/', 'src="' . $photo . '"', $html);

				return preg_replace('/srcset=".+?"/', 'srcset="' . $photo . '"', $html);
			}
		}
	}

	return $html;
}

add_filter('get_avatar', 'ulogin_get_avatar', 10, 5);
add_filter('wpua_get_avatar_filter', 'ulogin_get_avatar_wpua', 10, 5);
add_filter('wpua_get_avatar_original', 'ulogin_get_avatar_original_wpua', 10, 1);
/**
 * Для поддержки плагина Simplemodal Login Form
 */
add_filter('simplemodal_login_form', 'ulogin_simplemodal_login_form');
function ulogin_simplemodal_login_form($text) {
	return str_replace('<div class="simplemodal-login-fields">', '<div class="simplemodal-login-fields">' . get_ulogin_panel('uLoginSMLF'), $text);
}

/**
 * Ссылка на страницу настроек из списка плагинов
 */
add_filter('plugin_action_links', 'ulogin_plugin_action_links', 10, 2);
function ulogin_plugin_action_links($links, $file) {
	if(basename(dirname($file)) == 'ulogin') {
		$links[] = '<a href="' . add_query_arg(array('page' => 'ulogin'), admin_url('plugins.php')) . '">' . __('Settings') . '</a>';
	}

	return $links;
}

/**
 * Добавление страницы настроек
 */
function uLoginSettingsPage() {
	$ulPluginSettings = new uLoginPluginSettings();
	if(!isset($ulPluginSettings)) {
		wp_die(__('Plugin uLogin has been installed incorrectly.'));

		return;
	}
	if(function_exists('add_plugins_page')) {
		$uLogin_Plugin_Settings = add_plugins_page('uLogin Plugin Settings', 'uLogin', 'manage_options', basename(__FILE__), array(&$ulPluginSettings, 'print_admin_page'));
		add_action('load-' . $uLogin_Plugin_Settings, array(&$ulPluginSettings, 'uLogin_Plugin_add_help_tab'));
	}
}

/**
 * @deprecated
 * Возвращает код JavaScript-функции, устанавливающей параметры uLogin
 */
function ulogin_js_setparams() {
	$ulOptions = uLoginPluginSettings::getDefaultOptionsArray();
	if(is_array($ulOptions)) {
		$x_ulogin_params = '';
		foreach($ulOptions as $key => $value) {
			if($key != 'label') {
				$x_ulogin_params .= $key . '=' . $value . ';';
			}
		}

		return '<script type=text/javascript>ulogin_addr=function(id,comment) {' . 'document.getElementById(id).setAttribute("x-ulogin-params","' . $x_ulogin_params . 'redirect_uri="+encodeURIComponent((location.href.indexOf(\'#\') != -1 ? location.href.substr(0, location.href.indexOf(\'#\')) : location.href) + \'&ulogin=token\' + (comment?\'#commentform\':\'\')));' . '}</script>';
	}

	return '';
}

/**
 * @deprecated
 * Возвращает код div-а с кнопками uLogin
 */
function ulogin_div($id) {
	$ulOptions = uLoginPluginSettings::getDefaultOptionsArray();
	$panel = '';
	if(is_array($ulOptions)) {
		$panel = '<div style="float:left;line-height:24px">' . $ulOptions['label'] . '&nbsp;</div><div id=' . $id . ' data-ulogin="display=panel;fields=first_name,last_name;providers=vkontakte,odnoklassniki,mailru,facebook;hidden=other;></div><div style="clear:both"></div>';
	}

	return $panel;
}

/**
 * @deprecated
 * Возвращает код uLogin для формы добавления комментариев
 * для версии WP раннее 3.0.0
 */
function ulogin_comment_form() {
	global $current_user;
	if($current_user->ID == 0) {
		$version = uLoginPluginSettings::$_versionOfUloginScript;
		echo '<script src="//ulogin.ru/js/ulogin.js?version='.$version.'" type="text/javascript"></script>' . ulogin_js_setparams() . '<script type="text/javascript">' . '(function() {' . 'var form = document.getElementById(\'commentform\');' . 'if (form) {' . 'var div = document.createElement(\'div\');' . 'div.innerHTML = \'' . ulogin_div('uLogin') . '\';' . 'form.parentNode.insertBefore(div, form);' . 'ulogin_addr("uLogin",1);' . '}' . '})();' . '</script>';
	}
}

/**
 * Возвращает код uLogin для формы добавления комментариев
 */
function ulogin_comment_form_before_fields() {
	echo get_ulogin_panel(1);
}

/**
 * Возвращает код uLogin для формы входа и регистрации
 */
function ulogin_form_panel() {
	echo get_ulogin_panel(0);
}

/**
 * Возвращает код uLogin для отображения в произвольном месте
 * @param int $panel - номер uLogin панели, соответствует указанным в настройках плагина полям с ID (значение 0 - для
 *     первого плагина, 1 - для второго)
 * @param bool $with_label - указывает, стоит ли отображать строку типа "Войти с помощью:" рядом с виджетом (true -
 *     строка отображается)
 * @param bool $is_logining - указывает, отображать ли виджет, если пользователь залогинен (false - виджет скрывается)
 * @param string $id - id для div-панели (если не задан - генерируется автоматически)
 * @return string - код uLogin для отображения в произвольном месте
 */
function get_ulogin_panel($panel = 0, $with_label = true, $is_logining = false, $id = '') {
	global $current_user;

    //TODO заготовка для вывода виджета на странице wp-login.php
    $isLoginPage = parse_url(wp_login_url(), PHP_URL_PATH) === parse_url(ulogin_get_current_page_url(), PHP_URL_PATH);

	if(!$current_user->ID || $is_logining) {
		wp_enqueue_style('ulogin-style');
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
function ulogin_panel($id = '') {
	return get_ulogin_panel(0, false, false, $id);
}

/**
 * "Обменивает" токен на пользовательские данные
 * @param bool $token
 * @return bool|mixed|string
 */
function ulogin_get_user_from_token($token = false) {
	$response = false;
	if($token) {
		global $wp_version;
		$data = array('cms' => 'wordpress', 'version' => $wp_version,);
		$request = 'https://ulogin.ru/token.php?token=' . $token . '&host=' . $_SERVER['HTTP_HOST'] . '&data=' . base64_encode(json_encode($data));
		$response = ulogin_get_response($request);
	}

	return $response;
}

/**
 * Получение данных с помощью curl или file_get_contents
 * @param string $url
 * @return bool|mixed|string
 */
function ulogin_get_response($url = "") {
	$result = false;
	if(in_array('curl', get_loaded_extensions())) {
		$request = curl_init($url);
		curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($request, CURLOPT_BINARYTRANSFER, 1);
//		curl_setopt($request, CURLOPT_FOLLOWLOCATION, 1);
		$result = curl_exec($request);
	} elseif(function_exists('file_get_contents') && ini_get('allow_url_fopen')) {
		$result = file_get_contents($url);
	}

	return $result;
}

/**
 * Проверка пользовательских данных, полученных по токену
 * @param $u_user - пользовательские данные
 * @return bool
 */
function ulogin_check_token_error($u_user) {
	if(!is_array($u_user)) {
		wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" . "Данные о пользователе содержат неверный формат."), 'uLogin error', array('back_link' => true));

		return false;
	}
	if(isset($u_user['error'])) {
		$strpos = strpos($u_user['error'], 'host is not');
		if($strpos) {
			wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" . "<i>ERROR</i>: адрес хоста не совпадает с оригиналом") . sub($u_user['error'], intval($strpos) + 12), 'uLogin error', array('back_link' => true));
		}
		switch($u_user['error']) {
			case 'token expired':
				wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" . "<i>ERROR</i>: время жизни токена истекло"), 'uLogin error', array('back_link' => true));
				break;
			case 'invalid token':
				wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" . "<i>ERROR</i>: неверный токен"), 'uLogin error', array('back_link' => true));
				break;
			default:
				wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" . "<i>ERROR</i>: ") . $u_user['error'], 'uLogin error', array('back_link' => true));
		}

		return false;
	}
	if(!isset($u_user['identity'])) {
		wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" . "В возвращаемых данных отсутствует переменная <b>identity</b>."), 'uLogin error', array('back_link' => true));

		return false;
	}

	return true;
}

/**
 * @param $user_id
 * @return bool
 */
function ulogin_check_user_id($user_id) {
	global $current_user;
	if(($current_user->ID > 0) && ($user_id > 0) && ($current_user->ID != $user_id)) {
		wp_die(__("Данный аккаунт привязан к другому пользователю.</br>" . " Вы не можете использовать этот аккаунт"), 'uLogin warning', array('back_link' => true));

		return false;
	}

	return true;
}

/**
 * Обработка ответа сервера авторизации
 */
function ulogin_parse_request() {
	if(!isset($_POST['token'])) {
		wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" . "Не был получен токен uLogin."), 'uLogin error', array('back_link' => true));

		return;  // не был получен токен uLogin
	}
	$s = ulogin_get_user_from_token($_POST['token']);
	if(!$s) {
		wp_die(__("<b>Ошибка работы uLogin:</b></br></br>" . "Не удалось получить данные о пользователе с помощью токена."), 'uLogin error', array('back_link' => true));

		return;
	}
	$u_user = json_decode($s, true);
	if(!ulogin_check_token_error($u_user)) {
		return;
	}
	global $wpdb;
	uLoginPluginSettings::register_database_table();
	$user_id = $wpdb->get_var($wpdb->prepare("SELECT userid FROM $wpdb->ulogin where identity = %s", urlencode($u_user['identity'])));
	if(isset($user_id)) {
		$wp_user = get_userdata($user_id);
		if($wp_user->ID > 0 && $user_id > 0) {
			ulogin_check_user_id($user_id);
		} else {
			// данные о пользователе есть в ulogin_table, но отсутствуют в WP. Необходимо выполнить перерегистрацию в ulogin_table и регистрацию/вход в WP.
			$user_id = ulogin_registration_user($u_user, 1);
		}
	} else {
		// пользователь НЕ обнаружен в ulogin_table. Необходимо выполнить регистрацию в ulogin_table и регистрацию/вход в WP.
		$user_id = ulogin_registration_user($u_user);
	}
	// обновление данных и Вход
	if($user_id > 0) {
		ulogin_enter_user($u_user, $user_id);
	}
}

/**
 * Обновление данных о пользователе и вход
 * @param $u_user - данные о пользователе, полученные от uLogin
 * @param $user_id - идентификатор пользователя
 */
function ulogin_enter_user($u_user, $user_id) {
	global $uLoginOptions;
	
	$updating_data = array('user_email' => $u_user['email'], 'first_name' => $u_user['first_name'], 'last_name' => $u_user['last_name'], 'display_name' => $u_user['first_name'] . ' ' . $u_user['last_name']);
	$update_user_data = array('ID' => $user_id);
	$wp_user = get_userdata($user_id);

	if($uLoginOptions['set_url']) {
		$updating_data['user_url'] = $u_user['profile'];
	} else if($wp_user->user_url == $u_user['profile']) {
		$update_user_data['user_url'] = '';
	}
	foreach($updating_data as $datum => $value) {
		if(isset($value) && empty($wp_user->{$datum})) {
			$update_user_data[$datum] = $value;
		}
	}
	wp_update_user($update_user_data);
	if($avatar_url = ulogin_get_user_photo($u_user, $user_id)) {
		update_user_meta($user_id, 'ulogin_photo', $avatar_url);
	}
	wp_set_current_user($user_id);
	wp_set_auth_cookie($user_id, true);

	if($login_page = urldecode($_GET['backurl'])) {
		$login_page = preg_replace('/(&|\?)reauth=1$/', '', $login_page);
		$login_page = preg_replace('/(&|\?)reauth=1&/', '$1', $login_page);
	}

    $autoHome = home_url(); //автоматически определенный адрес главной страницы
    $handleHome = get_option('home'); //адрес главной, указанный в настройках в поле "Адрес сайта"
    $autoHomeScheme = parse_url($autoHome, PHP_URL_SCHEME);
    $handleHomeScheme = parse_url($handleHome, PHP_URL_SCHEME);
    $autoHome = str_replace($autoHomeScheme, $handleHomeScheme, home_url());

    do_action('ulogin_enter_user', $user_id);

    if(empty($login_page) || (parse_url($login_page, PHP_URL_PATH) === parse_url(wp_login_url(), PHP_URL_PATH))) {
		wp_redirect($autoHome);
	} else {
		ulogin_wp_redirect($login_page);
	}
	exit;
}

/**
 * Получение аватара пользователя
 * @param $u_user
 * @param $user_id
 * @return string
 */
function ulogin_get_user_photo($u_user, $user_id) {
	$q = true;
	$validate_gravatar = ulogin_validate_gravatar('', $user_id);
	if($validate_gravatar) {
		update_user_meta($user_id, 'ulogin_photo_gravatar', 1);

		return false;
	}
	delete_user_meta($user_id, 'ulogin_photo_gravatar');

	$u_user['photo'] = (!empty($u_user['photo']) && $u_user['photo'] === "https://ulogin.ru/img/photo.png") ? '' : $u_user['photo'];
	$u_user['photo_big'] = (empty($u_user['photo_big']) || $u_user['photo_big'] === "https://ulogin.ru/img/photo_big.png") ? '' : $u_user['photo_big'];

	$file_url = !empty($u_user['photo_big']) ? $u_user['photo_big'] : $u_user['photo'];

	if(empty($file_url)) {
		return false;
	}

	//directory to import to
	$avatar_dir = str_replace('\\', '/', dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-content/uploads/';
	if(!file_exists($avatar_dir)) {
		$q = mkdir($avatar_dir);
	}
	$avatar_dir .= 'ulogin_avatars/';
	if($q && !file_exists($avatar_dir)) {
		$q = mkdir($avatar_dir);
	}
	if(!$q) {
		return false;
	}
	$response = ulogin_get_response($file_url, false);
	$response = (!$response && in_array('curl', get_loaded_extensions())) ? file_get_contents($file_url) : $response;
	if(!$response) {
		return false;
	}
	$filename = $u_user['network'] . '_' . $u_user['uid'];
	$file_addr = $avatar_dir . $filename;
	$handle = fopen($file_addr, "w");
	$fileSize = fwrite($handle, $response);
	fclose($handle);
	if(!$fileSize) {
		@unlink($file_addr);

		return false;
	}
	list($width, $height, $type) = getimagesize($file_addr);
	if($width / $height > 1) {
		$max_size = $width;
	} else {
		$max_size = $height;
	}
	$thumb = wp_imagecreatetruecolor($max_size, $max_size);
	if(!is_resource($thumb)) {
		@unlink($file_addr);

		return false;
	}
	switch($type) {
		case IMAGETYPE_GIF:
			$res = '.gif';
			$source = imagecreatefromgif($file_addr);
			break;
		case IMAGETYPE_JPEG:
			$res = '.jpg';
			$source = imagecreatefromjpeg($file_addr);
			break;
		case IMAGETYPE_PNG:
			$res = '.png';
			$source = imagecreatefrompng($file_addr);
			break;
		default:
			$res = '.jpg';
			$source = imagecreatefromjpeg($file_addr);
			break;
	}
	if(imagecopy($thumb, $source, ($max_size - $width) / 2, ($max_size - $height) / 2, 0, 0, $width, $height)) {
		imagedestroy($source);
		@unlink($file_addr);
	} else {
		@unlink($file_addr);

		return false;
	}
	$filename = $filename . $res;
	switch($type) {
		case IMAGETYPE_GIF:
			imagegif($thumb, $avatar_dir . $filename);
			break;
		case IMAGETYPE_JPEG:
			imagejpeg($thumb, $avatar_dir . $filename);
			break;
		case IMAGETYPE_PNG:
			imagepng($thumb, $avatar_dir . $filename);
			break;
		default:
			imagejpeg($thumb, $avatar_dir . $filename);
			break;
	}
	imagedestroy($thumb);

	return home_url() . '/wp-content/uploads/ulogin_avatars/' . $filename;
}

/**
 * Redirects to another page.
 * @since 1.5.1
 * @param string $location The path to redirect to.
 * @param int $status Status code to use.
 * @return bool False if $location is not provided, true otherwise.
 */
function ulogin_wp_redirect($location, $status = 302) {
	global $is_IIS;
	/**
	 * Filter the redirect location.
	 * @since 2.1.0
	 * @param string $location The path to redirect to.
	 * @param int $status Status code to use.
	 */
	$location = apply_filters('wp_redirect', $location, $status);
	/**
	 * Filter the redirect status code.
	 * @since 2.3.0
	 * @param int $status Status code to use.
	 * @param string $location The path to redirect to.
	 */
	$status = apply_filters('wp_redirect_status', $status, $location);
	if(!$location)
		return false;
	if(!$is_IIS && php_sapi_name() != 'cgi-fcgi')
		status_header($status); // This causes problems on IIS and some FastCGI setups
	header("Location: $location", true, $status);

	return true;
}

/**
 * Регистрация на сайте и в таблице uLogin
 * @param Array $u_user - данные о пользователе, полученные от uLogin
 * @param int $in_db - при значении 1 необходимо переписать данные в таблице uLogin
 * @return bool|int|WP_Error
 */
function ulogin_registration_user($u_user, $in_db = 0) {
	global $wpdb, $uLoginOptions, $current_user;
	if(!isset($u_user['email'])) {
		wp_die(__("Через данную форму выполнить вход/регистрацию невозможно. </br>" . "Сообщиете администратору сайта о следующей ошибке: </br></br>" . "Необходимо указать <b>email</b> в возвращаемых полях <b>uLogin</b>"), 'uLogin warning', array('back_link' => true));

		return false;
	}
	$network = isset($u_user['network']) ? $u_user['network'] : '';
	// данные о пользователе есть в ulogin_table, но отсутствуют в WP
	if($in_db == 1) {
		$wpdb->query($wpdb->prepare("DELETE FROM $wpdb->ulogin where identity = %s", urlencode($u_user['identity'])));
	}
	$user_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->users where user_email = %s", $u_user['email']));
	// $check_m_user == true -> есть пользователь с таким email
	$check_m_user = $user_id > 0 ? true : false;
	
	// $isLoggedIn == true -> ползователь онлайн
	$isLoggedIn = $current_user->ID > 0 ? true : false;
	if(!$check_m_user && !$isLoggedIn) { // отсутствует пользователь с таким email в базе WP -> регистрация
		$user_login = ulogin_generateNickname($u_user['first_name'], $u_user['last_name'], isset($u_user['nickname']) ? $u_user['nickname'] : '', isset($u_user['bdate']) ? $u_user['bdate'] : '');
		$user_pass = wp_generate_password();
		$insert_user = array(
            'user_pass' => $user_pass,
            'user_login' => $user_login,
            'user_email' => $u_user['email'],
            'first_name' => $u_user['first_name'],
            'last_name' => $u_user['last_name'],
            'display_name' => $u_user['first_name'] . ' ' . $u_user['last_name']
        );
		
		if($uLoginOptions['set_url']) {
			$insert_user['user_url'] = $u_user['profile'];
		}
		$user_id = wp_insert_user($insert_user);
		if(!is_wp_error($user_id) && $user_id > 0 && ($uLoginOptions['new_user_notification'] == true)) {
			wp_new_user_notification($user_id, $user_pass);
		}

		do_action('ulogin_registration', $user_id);

		return ulogin_insert_row($user_id, $u_user['identity'], $network);
	} else { // существует пользователь с таким email или это текущий пользователь
		if(!isset($u_user["verified_email"]) || intval($u_user["verified_email"]) != 1) {
			$version = uLoginPluginSettings::$_versionOfUloginScript;
			wp_die('<script src="//ulogin.ru/js/ulogin.js?version='.$version.'"  type="text/javascript"></script><script type="text/javascript">uLogin.mergeAccounts("' . $_POST['token'] . '")</script>' . __("Электронный адрес данного аккаунта совпадает с электронным адресом существующего пользователя. <br>Требуется подтверждение на владение указанным email.</br></br>"), __("Подтверждение аккаунта"), array('back_link' => true));

			return false;
		}
		if(intval($u_user["verified_email"]) == 1) {
			$user_id = $isLoggedIn ? $current_user->ID : $user_id;
			$other_u = $wpdb->get_col($wpdb->prepare("SELECT identity FROM $wpdb->ulogin where userid = %d", $user_id));
			if($other_u) {
				if(!$isLoggedIn && !isset($u_user['merge_account'])) {
					$version = uLoginPluginSettings::$_versionOfUloginScript;
					wp_die('<script src="//ulogin.ru/js/ulogin.js?version='.$version.'"  type="text/javascript"></script><script type="text/javascript">uLogin.mergeAccounts("' . $_POST['token'] . '","' . $other_u[0] . '")</script>' . __("С данным аккаунтом уже связаны данные из другой социальной сети. <br>Требуется привязка новой учётной записи социальной сети к этому аккаунту."), __("Синхронизация аккаунтов"), array('back_link' => true));

					return false;
				}
			}

			return ulogin_insert_row($user_id, $u_user['identity'], $network);
		}
	}

	return false;
}

/**
 * Добавление новой привязки uLogin
 * в случае успешного выполнения возвращает $user_id иначе - wp_die с сообщением об ошибке
 * @param $user_id
 * @param $identity
 * @param string $network
 * @return bool
 */
function ulogin_insert_row($user_id, $identity, $network = '') {
	global $wpdb;
	if(!is_wp_error($user_id) && $user_id > 0) {
		$err = $wpdb->insert($wpdb->ulogin, array('userid' => $user_id, 'identity' => urlencode($identity), 'network' => $network), array('%d', '%s', '%s'));
		if($err !== false) {
			return $user_id;
		}
	} elseif(is_wp_error($user_id)) {
		$err = $user_id;
	}
	if(is_wp_error($err)) {
		wp_die($err->get_error_message(), '', array('back_link' => true));
	} elseif($err === false)
		wp_die('Произошла ошибка при добавлении аккаунта', 'uLogin error', array('back_link' => true));

	return false;
}

/**
 * Гнерация логина пользователя
 * в случае успешного выполнения возвращает уникальный логин пользователя
 * @param $first_name
 * @param string $last_name
 * @param string $nickname
 * @param string $bdate
 * @param array $delimiters
 * @return string
 */
function ulogin_generateNickname($first_name, $last_name = "", $nickname = "", $bdate = "", $delimiters = array('.', '_')) {
	$delim = array_shift($delimiters);
	$first_name = ulogin_translitIt($first_name);
	$first_name_s = substr($first_name, 0, 1);
	$variants = array();
	if(!empty($nickname))
		$variants[] = $nickname;
	$variants[] = $first_name;
	if(!empty($last_name)) {
		$last_name = ulogin_translitIt($last_name);
		$variants[] = $first_name . $delim . $last_name;
		$variants[] = $last_name . $delim . $first_name;
		$variants[] = $first_name_s . $delim . $last_name;
		$variants[] = $first_name_s . $last_name;
		$variants[] = $last_name . $delim . $first_name_s;
		$variants[] = $last_name . $first_name_s;
	}
	if(!empty($bdate)) {
		$date = explode('.', $bdate);
		$variants[] = $first_name . $date[2];
		$variants[] = $first_name . $delim . $date[2];
		$variants[] = $first_name . $date[0] . $date[1];
		$variants[] = $first_name . $delim . $date[0] . $date[1];
		$variants[] = $first_name . $delim . $last_name . $date[2];
		$variants[] = $first_name . $delim . $last_name . $delim . $date[2];
		$variants[] = $first_name . $delim . $last_name . $date[0] . $date[1];
		$variants[] = $first_name . $delim . $last_name . $delim . $date[0] . $date[1];
		$variants[] = $last_name . $delim . $first_name . $date[2];
		$variants[] = $last_name . $delim . $first_name . $delim . $date[2];
		$variants[] = $last_name . $delim . $first_name . $date[0] . $date[1];
		$variants[] = $last_name . $delim . $first_name . $delim . $date[0] . $date[1];
		$variants[] = $first_name_s . $delim . $last_name . $date[2];
		$variants[] = $first_name_s . $delim . $last_name . $delim . $date[2];
		$variants[] = $first_name_s . $delim . $last_name . $date[0] . $date[1];
		$variants[] = $first_name_s . $delim . $last_name . $delim . $date[0] . $date[1];
		$variants[] = $last_name . $delim . $first_name_s . $date[2];
		$variants[] = $last_name . $delim . $first_name_s . $delim . $date[2];
		$variants[] = $last_name . $delim . $first_name_s . $date[0] . $date[1];
		$variants[] = $last_name . $delim . $first_name_s . $delim . $date[0] . $date[1];
		$variants[] = $first_name_s . $last_name . $date[2];
		$variants[] = $first_name_s . $last_name . $delim . $date[2];
		$variants[] = $first_name_s . $last_name . $date[0] . $date[1];
		$variants[] = $first_name_s . $last_name . $delim . $date[0] . $date[1];
		$variants[] = $last_name . $first_name_s . $date[2];
		$variants[] = $last_name . $first_name_s . $delim . $date[2];
		$variants[] = $last_name . $first_name_s . $date[0] . $date[1];
		$variants[] = $last_name . $first_name_s . $delim . $date[0] . $date[1];
	}
	$i = 0;
	$exist = true;
	while(true) {
		if($exist = ulogin_userExist($variants[$i])) {
			foreach($delimiters as $del) {
				$replaced = str_replace($delim, $del, $variants[$i]);
				if($replaced !== $variants[$i]) {
					$variants[$i] = $replaced;
					if(!$exist = ulogin_userExist($variants[$i]))
						break;
				}
			}
		}
		if($i >= count($variants) - 1 || !$exist)
			break;
		$i++;
	}
	if($exist) {
		while($exist) {
			$nickname = $first_name . mt_rand(1, 100000);
			$exist = ulogin_userExist($nickname);
		}

		return $nickname;
	} else
		return $variants[$i];
}

/**
 * Проверка существует ли пользователь с заданным логином
 */
function ulogin_userExist($login) {
	if(get_user_by('login', $login) === false) {
		return false;
	}

	return true;
}

/**
 * Транслит
 * @param $str
 * @return mixed|string
 */
function ulogin_translitIt($str) {
	$tr = array("А" => "a", "Б" => "b", "В" => "v", "Г" => "g", "Д" => "d", "Е" => "e", "Ж" => "j", "З" => "z", "И" => "i", "Й" => "y", "К" => "k", "Л" => "l", "М" => "m", "Н" => "n", "О" => "o", "П" => "p", "Р" => "r", "С" => "s", "Т" => "t", "У" => "u", "Ф" => "f", "Х" => "h", "Ц" => "ts", "Ч" => "ch", "Ш" => "sh", "Щ" => "sch", "Ъ" => "", "Ы" => "yi", "Ь" => "", "Э" => "e", "Ю" => "yu", "Я" => "ya", "а" => "a", "б" => "b", "в" => "v", "г" => "g", "д" => "d", "е" => "e", "ж" => "j", "з" => "z", "и" => "i", "й" => "y", "к" => "k", "л" => "l", "м" => "m", "н" => "n", "о" => "o", "п" => "p", "р" => "r", "с" => "s", "т" => "t", "у" => "u", "ф" => "f", "х" => "h", "ц" => "ts", "ч" => "ch", "ш" => "sh", "щ" => "sch", "ъ" => "y", "ы" => "y", "ь" => "", "э" => "e", "ю" => "yu", "я" => "ya");
	if(preg_match('/[^A-Za-z0-9\_\-]/', $str)) {
		$str = strtr($str, $tr);
		$str = preg_replace('/[^A-Za-z0-9\_\-\.]/', '', $str);
	}

	return $str;
}

/**
 * Возвращает текущий url
 */
function ulogin_get_current_page_url() {
	$pageURL = 'http';
	if(isset($_SERVER["HTTPS"])) {
		if($_SERVER["HTTPS"] == "on") {
			$pageURL .= "s";
		}
	}
	$pageURL .= "://";
	if($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") {
		$pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
	}

	return $pageURL;
}

/**
 * функция, вызывающаяся при удалении пользователя. Очищает связанные с ним данные из таблиц ulogin
 * @param $user_id
 */
function ulogin_delete_user($user_id) {
    global $wpdb;
    uLoginPluginSettings::register_database_table();
    $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->ulogin where userid = %d", $user_id));
}

/**
 * Проверка, имеется ли аватар Gravatar у пользователя
 */
function ulogin_validate_gravatar($email = '', $id = 0) {
	if($email == '') {
		$email = get_user_by('id', $id);
		$email = $email->user_email;
	}
	$hash = md5(strtolower(trim($email)));
	$uri = 'http://www.gravatar.com/avatar/' . $hash . '?d=404';
	$response = wp_remote_head($uri);

	return !is_wp_error($response) && $response['response']['code'] == '200';
}

/**
 * Возвращает url аватара пользователя
 * @param $avatar
 * @param $id_or_email
 * @param $size
 * @param $default
 * @param $alt
 * @return mixed
 */
function ulogin_get_avatar($avatar, $id_or_email, $size, $default, $alt) {
	$soc_avatar = uLoginPluginSettings::getOptions();
	$soc_avatar = $soc_avatar['social_avatar'];
	$default_avatar = get_option('avatar_default');

	//нормализуем данные про аватары к единому виду
	if($default == '')
		$default = 'gravatar_default';
	if(!in_array($default, array ( 'blank', 'gravatar_default', 'identicon', 'wavatar', 'monsterid', 'retro' )))
		$default = 'mm';
	switch($default_avatar) {
		case 'mystery':
			$default_avatar = 'mm';
			break;
	}

	//если аватар отличается от дефолтного стиля, то выводить иконку
	if($default != $default_avatar) {
		return $avatar;
	}
	$user_id = parce_id_or_email($id_or_email);
	$user_id = $user_id['id'];
	if(get_user_meta($user_id, 'wp_user_avatar', 1)) {
		return $avatar;
	}
	$photo = get_user_meta($user_id, 'ulogin_photo', 1);
	if($photo && $soc_avatar) {
		$avatar = preg_replace('/src=([^\s]+)/i', 'src="' . $photo . '"', $avatar);
		$avatar = preg_replace('/srcset=([^\s]+)/i', 'srcset="' . $photo . '"', $avatar);
	}

	return $avatar;
}

/**
 * Возвращает url аватара пользователя для плагина wp-user-avatar
 */
function ulogin_get_avatar_wpua($avatar, $id_or_email, $size, $default, $alt) {
	if($default != 'wp_user_avatar') {
		return $avatar;
	}
	$soc_avatar = uLoginPluginSettings::getOptions();
	$soc_avatar = $soc_avatar['social_avatar'];
	$user_id = parce_id_or_email($id_or_email);
	$user_id = $user_id['id'];
	$default_avatar = get_option('avatar_default');

	if($default == '')
		$default = 'gravatar_default';
	if(!in_array($default, array ( 'blank', 'gravatar_default', 'identicon', 'wavatar', 'monsterid', 'retro' )))
		$default = 'mm';
	switch($default_avatar) {
		case 'mystery':
			$default_avatar = 'mm';
			break;
	}
	if($default != $default_avatar) {
		return $avatar;
	}

	$photo = get_user_meta($user_id, 'ulogin_photo', 1);
	if($photo && $soc_avatar) {
		$avatar = preg_replace('/src=([^\s]+)/i', 'src="' . $photo . '"', $avatar);
		$avatar = preg_replace('/srcset=([^\s]+)/i', 'srcset="' . $photo . '"', $avatar);
	}

	return $avatar;
}

/**
 * Возвращает url аватара по умолчанию пользователя для плагина wp-user-avatar
 */
function ulogin_get_avatar_original_wpua($default) {
	global $current_user;
	$user_id = $current_user->ID;
	$photo = $user_id > 0 ? get_user_meta($user_id, 'ulogin_photo', 1) : $default;

	return $photo ? $photo : $default;
}

/**
 * Преобразует переменную $id_or_email в массив
 */
function parce_id_or_email($id_or_email) {
	$email = '';
	$user_id = 0;
	if(is_numeric($id_or_email)) {
		$user_id = @get_user_by('id', (int)$id_or_email)->ID;
	} elseif(is_object($id_or_email)) {
		if(!empty($id_or_email->user_id)) {
			$user_id = $id_or_email->user_id;
		} elseif(!empty($id_or_email->comment_author_email)) {
			$email = $id_or_email->comment_author_email;
			$user_id = @get_user_by('email', $email)->ID;
		}
	} else {
		$email = $id_or_email;
		$user_id = @get_user_by('email', $email)->ID;
	}

	return array('id' => $user_id, 'email' => $email);
}

/**
 * Удаление привязок к аккаунтам социальных сетей
 */
function ulogin_deleteaccount_request() {
	if(!is_user_logged_in())
		exit;
	global $current_user;
	$user_id = $current_user->ID;
	$network = isset($_POST['network']) ? $_POST['network'] : '';
	if($user_id != '' && $network != '') {
		try {
			global $wpdb;
			uLoginPluginSettings::register_database_table();
			$wpdb->query($wpdb->prepare("DELETE FROM $wpdb->ulogin where userid = %d and network = '%s'", $user_id, $network));
			echo json_encode(array('title' => "Удаление аккаунта", 'msg' => "Удаление аккаунта $network успешно выполнено", 'user' => $user_id, 'answerType' => 'ok'));
			exit;
		} catch(Exception $e) {
			echo json_encode(array('title' => "Ошибка при удалении аккаунта", 'msg' => "Exception: " . $e->getMessage(), 'answerType' => 'error'));
			exit;
		}
	}
	exit;
}

/**
 * Добавление формы синхронизации в профиль пользователя
 */
function ulogin_profile_personal_options($user) {
	ulogin_synchronisation_panel($user->ID);
}

/**
 * Добавление формы синхронизации в профиль пользователя в Buddypress
 */
function ulogin_bp_custom_profile_edit_fields() {
	global $current_user;
	echo '<br>';
	$user_id = $current_user->ID;
	if($user_id == bp_current_user_id() && bp_current_user_id() == bp_displayed_user_id() && bp_current_user_id() > 0) {
		ulogin_synchronisation_panel($user_id);
	}
}

/**
 * Вывод списка аккаунтов пользователя
 * @param int $user_id - ID пользователя (если не задан - текущий пользователь)
 * @return string
 */
function get_ulogin_user_accounts_panel($user_id = 0) {
	global $wpdb, $current_user;
	uLoginPluginSettings::register_database_table();
	wp_enqueue_style('ulogin-prov-style');
	wp_enqueue_style('ulogin-style');
	$user_id = empty($user_id) ? $current_user->ID : $user_id;
	if(empty($user_id))
		return '';
	$networks = $wpdb->get_col($wpdb->prepare("SELECT network FROM $wpdb->ulogin where userid = %d", $user_id), 0);
	$output = '';
	if($networks) {
		$output .= '<div id="ulogin_accounts">';
		foreach($networks as $network) {
			$output .= "<div data-ulogin-network='{$network}'
                 class='ulogin_network big_provider {$network}_big'></div>";
		}
		$output .= '</div>';
	}

	return $output;
}

/**
 * Вывод формы синхронизации
 */
function ulogin_synchronisation_panel() {
	if(!is_user_logged_in())
		return;
	$str_info['h3'] = __("Синхронизация аккаунтов");
	$str_info['str1'] = __("Синхронизация аккаунтов");
	$str_info['str2'] = __("Привязанные аккаунты");
	$str_info['about1'] = __("Привяжите ваши аккаунты соц. сетей к личному кабинету для быстрой авторизации через любой из них");
	$str_info['about2'] = __("Вы можете удалить привязку к аккаунту, кликнув по значку");

	?>
	<script type="text/javascript">
		jQuery(document).ready(function () {
			var uloginNetwork = jQuery('#ulogin_synchronisation').find('.ulogin_network');
			uloginNetwork.click(function () {
				var network = jQuery(this).data('uloginNetwork');
				uloginDeleteAccount(network);
			});
		});

		function uloginDeleteAccount(network) {
			jQuery.ajax({
				url: '/?ulogin=deleteaccount',
				type: 'POST',
				dataType: 'json',
				cache: false,
				data: {
					network: network
				},
				error: function (data, textStatus, errorThrown) {
					console.log('error');
					alert('Не удалось выполнить запрос');
				},
				success: function (data) {
					console.log('success');
					switch (data.answerType) {
						case 'error':
							alert(data.title + "\n" + data.msg);
							break;
						case 'ok':
							var accounts = jQuery('#ulogin_accounts'),
								nw = accounts.find('[data-ulogin-network=' + network + ']');
							if (nw.length > 0) nw.hide();
							break;
						default:
							break;
					}
				}
			});
		}
	</script>
	<div id="ulogin_synchronisation">
		<h3 title="<?php echo $str_info['about1'] ?>"><?php echo $str_info['h3'] ?></h3>
		<table class="form-table">
			<tr>
				<th><label><?php echo $str_info['str1'] ?></label></th>
				<td> <?php echo get_ulogin_panel(2, false, true); ?>
					<span class="description"><?php echo $str_info['about1'] ?></span>
			</tr>
			<tr>
				<th><label><?php echo $str_info['str2'] ?></label></th>
				<td> <?php echo get_ulogin_user_accounts_panel() ?>
					<span class="description"><?php echo $str_info['about2'] ?></span>
			</tr>
		</table>
	</div>
<?php
}