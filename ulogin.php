<?php
/*
Plugin Name: uLogin - виджет авторизации через социальные сети
Plugin URI: http://ulogin.ru/
Description: uLogin
Version: 1.8.1
Author: uLogin
Author URI: http://ulogin.ru/
License: GPL2
*/

require_once 'settings.ulogin.php';

/*
 * Установка настроек плагина
 */


/*
 * Создание хуков
 */

global $current_user;

add_action('admin_menu', uLoginSettingsPage);
add_action('comment_form', ulogin_comment_form);
add_action('login_form', ulogin_form_panel);
add_action('register_form',ulogin_form_panel);
add_action('parse_request', ulogin_parse_request);
add_action('login_form_login', ulogin_parse_request);
add_action('register_post', ulogin_parse_request);
//add_filter('get_avatar', ulogin_get_avatar, $current_user->ID);
add_filter('simplemodal_login_form', ulogin_simplemodal_login_form);
add_filter('get_avatar', 'ulogin_get_avatar', 10, 2);
/*
 * Добавляет странице настроек
 */
function uLoginSettingsPage() {
    $ulPluginSettings = new uLoginPluginSettings();
    $ulPluginSettings->init();
    if (!isset($ulPluginSettings)) {
        return;
    }
    if (function_exists('add_options_page')) {
        add_submenu_page('plugins.php', 'uLogin Plugin Settings', 'uLogin', 9, basename(__FILE__), array(&$ulPluginSettings, 'printAdminPage'));
    }
}	

/*
 * Добавляет панель uLogin в simplemodal_login_form
 */
function ulogin_simplemodal_login_form($text) {
	return str_replace('<div class="simplemodal-login-fields">', '<div class="simplemodal-login-fields">' . ulogin_panel('uLoginSMLF'), $text);
}

/* 
 * Возвращает код JavaScript-функции, устанавливающей параметры uLogin
 */
function ulogin_js_setparams() {
    $ulPluginSettings = new uLoginPluginSettings();
    $ulPluginSettings->init();
    $ulOptions = $ulPluginSettings->getOptions();
    if(is_array($ulOptions)) {
            $x_ulogin_params = '';
            foreach ($ulOptions as $key=>$value){
                $x_ulogin_params.= $key.'='.$value.';';
            }
            return 	'<script type=text/javascript>ulogin_addr=function(id,comment) {'.
			'document.getElementById(id).setAttribute("x-ulogin-params","'.$x_ulogin_params.'redirect_uri="+encodeURIComponent((location.href.indexOf(\'#\') != -1 ? location.href.substr(0, location.href.indexOf(\'#\')) : location.href)+ (comment?\'#commentform\':\'\')));'.
			'}</script>';
	}
	return '';
}

/* 
 * Возвращает код div-а с кнопками uLogin
 */
function ulogin_div($id) {
    $ulPluginSettings = new uLoginPluginSettings();
    $ulPluginSettings->init();
    $ulOptions = $ulPluginSettings->getOptions();
    $panel = '';
    if (is_array($ulOptions)){
        if ($ulOptions['display'] != 'window')
            $panel = '<div style="float:left;line-height:24px">'.$ulOptions['label'].'&nbsp;</div><div id="'.$id.'" style="float:left"></div><div style="clear:both"></div>';
        else
            $panel = '<div style="float:left;line-height:24px">'.$ulOptions['label'].'&nbsp;</div><a href="#" id="'.$id.'" style="float:left"><img src="http://ulogin.ru/img/button.png" width=187 height=30 alt="МультиВход"/></a><div style="clear:both"></div>';
    }
    return $panel ;
}

/* Возвращает код uLogin для формы добавления комментариев
 * 
 */
function ulogin_comment_form() {
	$ulPluginSettings = new uLoginPluginSettings();
        $ulPluginSettings->init();
        $ulOptions = $ulPluginSettings->getOptions();
        global $current_user;
        if ($current_user->ID == 0) {
		echo 	'<script src="http://ulogin.ru/js/ulogin.js" type="text/javascript"></script>'.
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

/*
 * Возвращает код uLogin для отображения в произвольном месте
 */
function ulogin_panel($id='') {
	global $current_user;
	if (!$current_user->ID) {
		global $ulogin_counter;
		$ulogin_counter ++;
		$id=($id==''?'uLogin'.$ulogin_counter:$id);
                $panel ='<div><script src="http://ulogin.ru/js/ulogin.js" type="text/javascript"></script>'.ulogin_js_setparams().ulogin_div($id).'</div><script type="text/javascript">ulogin_addr("'.$id.'");uLogin.initWidget("'.$id.'");</script>';
                
	}
	return $panel;
}

/*
 * Обработка ответа сервера авторизации
 */
function ulogin_parse_request() {

	if (isset($_POST['token'])) {

		$s = get_user_from_token($_POST['token']);

        if (!$s)
            return;

		$user = json_decode($s, true);

		if (isset($user['uid'])) {

			$user_id = get_user_by('login', 'ulogin_' . $user['network'] . '_' . $user['uid']);

			if (isset($user_id->ID)) {

                if ($user['profile'] != $user_id->data->user_url){

                    wp_update_user(array('ID'=>$user_id, 'user_url' => $user['profile']));

                }

                $user_id = $user_id->ID;

			} else {

				$user_id = wp_insert_user(array('user_pass' => wp_generate_password(),
                                                'user_login' => 'ulogin_' . $user['network'] . '_' . $user['uid'],
                                                'user_url' => $user['profile'],
                                                'user_email' => $user['email'],
                                                'first_name' => $user['first_name'],
                                                'last_name' => $user['last_name'],
                                                'display_name' => $user['first_name'] . ' ' . $user['last_name'],
                                                'nickname' => $user['first_name'] . ' ' . $user['last_name']));
				$i = 0;
				$email = explode('@', $user['email']);

				while (!is_int($user_id)) {

					$i++;
					$user_id = wp_insert_user(array('user_pass' => wp_generate_password(),
                                                    'user_login' => 'ulogin_' . $user['network'] . '_' . $user['uid'],
                                                    'user_url' => $user['profile'],
                                                    'user_email' => $email[0] . '+' . $i . '@' . $email[1],
                                                    'first_name' => $user['first_name'],
                                                    'last_name' => $user['last_name'],
                                                    'display_name' => $user['first_name'] . ' ' . $user['last_name'],
                                                    'nickname' => $user['first_name'] . ' ' . $user['last_name']));

				}
			}
			update_usermeta($user_id, 'ulogin_photo', $user['photo']);
			wp_set_current_user($user_id);
			wp_set_auth_cookie($user_id);

            //правка от 12.09.2013: возврат на исходную страницу после авторизации
            $redirect_to = !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : $_SERVER['REQUEST_URI'];
            wp_redirect($redirect_to);
		}
	}
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
        if ($photo)
                return preg_replace('/src=([^\s]+)/i', 'src="' . $photo . '"', $avatar);

        return $avatar;
}
/*
 * Выводит в форму html для генерации виджета
 */
function ulogin_form_panel($id='uLogin_form'){
    $ulPluginSettings = new uLoginPluginSettings();
    $ulPluginSettings->init();
    $ulOptions = $ulPluginSettings->getOptions();
    $x_ulogin_params = '';
    foreach ($ulOptions as $key=>$value){
        $x_ulogin_params.= $key.'='.$value.';';
    }
    $x_ulogin_params.= 'redirect_uri='.urlencode(current_page_url());
    $script = '<script src="http://ulogin.ru/js/ulogin.js" type="text/javascript"></script>';
    $panel = '<div id='.$id.' x-ulogin-params="'.$x_ulogin_params.'"></div><br/>';
    
    if ($ulOptions['display'] == 'window'){
        $panel = '<a href="#" id="'.$id.'" x-ulogin-params="'.$x_ulogin_params.'"><img src="http://ulogin.ru/img/button.png" width=187 height=30 alt="МультиВход"/></a>';
    }
    
    echo $script.$panel;
}




/*
 * Возвращает текущий url
 */
function current_page_url() {
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
 * "Обменивает" токен на пользовательские данные
 *
 */
function get_user_from_token($token = false)
{
    $response = false;
    $request = 'http://ulogin.ru/token.php?token=' . $token . '&host=' . $_SERVER['HTTP_HOST'];

    if (function_exists('file_get_contents') && ini_get('allow_url_fopen')){

        $response = file_get_contents($request);

    }elseif(in_array('curl', get_loaded_extensions())){

        curl_init($request);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($request);

    } else {

        return;

    }

    return $response;

}

?>
