<?php

/*
 * uLogin Settings class
 */

if (!class_exists("uLoginPluginSettings")) {
    class uLoginPluginSettings{
        private $_uLoginOptionsName;
        private $_uLoginOptions;

        public function init(){
            $this->_uLoginOptionsName = 'uLoginPluginOptions';
            $this->_uLoginOptions = array(
                                        'display' => 'small',
                                        'providers' => 'vkontakte,odnoklassniki,mailru,facebook',
                                        'hidden' => 'other',
                                        'fields' => 'first_name,last_name,email,photo',
                                        'optional' => 'phone',
                                        'label' => 'Войти с помощью:'
            );
            $this->getOptions();
        }

        public function getOptions(){
            $uLoginOptions = get_option($this->_uLoginOptionsName);

            if (!empty($uLoginOptions)) {
                foreach ($uLoginOptions as $key => $option){
                    $this->_uLoginOptions[$key] = $option;
                }
            }

            update_option($this->_uLoginOptionsName, $this->_uLoginOptions);
            return $this->_uLoginOptions;
        }

       function printAdminPage() {
            $this->_uLoginOptionsName = 'uLoginPluginOptions';
            $uLoginOptions = $this->getOptions();
            if (isset($_POST['update_uLoginPluginSettings'])) {
                if (isset($_POST['uloginType'])) {
                    $uLoginOptions['display'] = $_POST['uloginType'] ;
                }
                if (isset($_POST['uloginProvs'])) {
                    $uLoginOptions['providers'] = empty($_POST['uloginProvs']) ? $uLoginOptions['providers'] : $_POST['uloginProvs'];
                }
                if (isset($_POST['uloginHidden'])) {
                    $uLoginOptions['hidden'] = $_POST['uloginHidden'];
                }
                if (isset($_POST['uloginFields'])) {
                    $uLoginOptions['fields'] = empty($_POST['uloginFields']) ? $uLoginOptions['fields'] : $_POST['uloginFields'];
                }
                if (isset($_POST['uloginOptional'])) {
                    $uLoginOptions['optional'] = $_POST['uloginOptional'];
                }
                if (isset($_POST['uloginLabel'])) {
                    $uLoginOptions['label'] = $_POST['uloginLabel'];
                }
                update_option($this->_uLoginOptionsName, $uLoginOptions);
            }    

            $form = file_get_contents('templates/settings.form.html',true);
            $form = str_replace('{URI}', $_SERVER['REQUEST_URI'], $form);
            $form = str_replace('{'.strtoupper($uLoginOptions['display']).'_SELECTED}','selected ="selected"', $form);
            $form = str_replace('{PROVIDERS}',  $uLoginOptions['providers'], $form);
            $form = str_replace('{HIDDEN}',  $uLoginOptions['hidden'], $form);
            $form = str_replace('{FIELDS}', $uLoginOptions['fields'], $form);
            $form = str_replace('{OPTIONAL}', $uLoginOptions['optional'], $form);
            $form = str_replace('{LABEL}', $uLoginOptions['label'], $form);
            echo $form;
        }
    }
}
?>
