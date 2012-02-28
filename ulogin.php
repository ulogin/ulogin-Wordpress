<?php
/*
Plugin Name: uLogin - виджет авторизации через социальные сети
Plugin URI: http://ulogin.ru/
Description: uLogin
Version: 1.7
Author: uLogin
Author URI: http://ulogin.ru/
License: GPL2
*/


add_action('comment_form', ulogin_comment_form);
add_action('parse_request', ulogin_parse_request);
add_filter('get_avatar', ulogin_get_avatar);
add_filter('simplemodal_login_form', ulogin_simplemodal_login_form);
function ulogin_simplemodal_login_form($text) {
	return str_replace('<div class="simplemodal-login-fields">', '<div class="simplemodal-login-fields">' . ulogin_panel('uLoginSMLF'), $text);
}
/* Возвращает код JavaScript-функции, устанавливающей параметры uLogin
*/
function ulogin_js_setparams() {
	global $ulogin_js_setparams;
	if($ulogin_js_setparams!==1) {
		$ulogin_js_setparams=1;
		return 	'<script type=text/javascript>ulogin_addr=function(id,comment) {'.
			'document.getElementById(id).setAttribute("x-ulogin-params","display=small&fields=first_name,last_name,email,photo&providers=vkontakte,odnoklassniki,mailru,facebook&hidden=twitter,google,yandex,livejournal,openid&redirect_uri="+encodeURIComponent((location.href.indexOf(\'#\') != -1 ? location.href.substr(0, location.href.indexOf(\'#\')) : location.href)+ (comment?\'#commentform\':\'\')));'.
			'}</script>';
	}
	return '';
}
/* Возвращает код div-а с кнопками uLogin
*/
function ulogin_div($id) {
	return '<div style="float:left;line-height:24px">Войти с помощью:&nbsp;</div><div id="'.$id.'" style="float:left"></div><div style="clear:both"></div>';
}
/* Возвращает код uLogin для формы добавления комментариев
*/
function ulogin_comment_form() {
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
/*Возвращает код uLogin для отображения в произвольном месте
*/
function ulogin_panel($id='') {
	global $current_user;
	if ($current_user->ID == 0) {
		global $ulogin_counter;
		$ulogin_counter++;
		$id=($id==''?'uLogin'.$ulogin_counter:$id);
		return	'<div>'.
			'<script src="http://ulogin.ru/js/ulogin.js" type="text/javascript"></script>'.
			ulogin_js_setparams().
			ulogin_div($id).
			'</div>'.
			'<script type="text/javascript">(function() {ulogin_addr("'.$id.'");})();</script>';
	}
	return '';
}
/*Обработка ответа сервера авторизации
*/
function ulogin_parse_request() {
	if (isset($_POST['token'])) {
		$s = file_get_contents('http://ulogin.ru/token.php?token=' . $_POST['token'] . '&host=' . $_SERVER['HTTP_HOST']);
		$user = json_decode($s, true);
		if (isset($user['uid'])) {
			$user_id = get_user_by('login', 'ulogin_' . $user['network'] . '_' . $user['uid']);
			if (isset($user_id->ID)) {
				$user_id = $user_id->ID;
			} else {
				$user_id = wp_insert_user(array('user_pass' => wp_generate_password(), 'user_login' => 'ulogin_' . $user['network'] . '_' . $user['uid'], 'user_url' => $user['identity'], 'user_email' => $user['email'], 'first_name' => $user['first_name'], 'last_name' => $user['last_name'], 'display_name' => $user['first_name'] . ' ' . $user['last_name'], 'nickname' => $user['first_name'] . ' ' . $user['last_name']));
				$i = 0;
				$email = explode('@', $user['email']);
				while (!is_int($user_id)) {
					$i++;
					$user_id = wp_insert_user(array('user_pass' => wp_generate_password(), 'user_login' => 'ulogin_' . $user['network'] . '_' . $user['uid'], 'user_url' => $user['identity'], 'user_email' => $email[0] . '+' . $i . '@' . $email[1], 'first_name' => $user['first_name'], 'last_name' => $user['last_name'], 'display_name' => $user['first_name'] . ' ' . $user['last_name'], 'nickname' => $user['first_name'] . ' ' . $user['last_name']));
				}
			}
			update_usermeta($user_id, 'ulogin_photo', $user['photo']);
			wp_set_current_user($user_id);
			wp_set_auth_cookie($user_id);
		}
	}
}
/*Возвращает url аватарки пользователя
*/
function ulogin_get_avatar($text) {
	global $comment;
	$user = get_userdata($comment->user_id);
	$network = $user->user_login;
	if (strpos($network, 'ulogin_') !== false) {
		$photo = get_usermeta($comment->user_id, 'ulogin_photo');
		return preg_replace('/src=([^\s]+)/i', 'src="' . $photo . '"', $text);
	} else return $text;
}