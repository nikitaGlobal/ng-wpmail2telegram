<?php

/**
Plugin Name: ng-mail2telegram
Plugin URI: http://nikita.global
Description: decription
Author: Nikita Menshutin
Version: 1.0
Author URI: http://nikita.global

PHP version 7.2
 *
@category NikitaGlobal
@package  NikitaGlobal
@author   Nikita Menshutin <nikita@nikita.global>
@license  http://nikita.global commercial
@link     http://nikita.global
 * */
defined('ABSPATH') or die("No script kiddies please!");
if (!class_exists("Ngmailtelegram")) {
    /**
     * Our main class goes here
     *
     * @category NikitaGlobal
     * @package  NikitaGlobal
     * @author   Nikita Menshutin <wpplugins@nikita.global>
     * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
     * @link     http://nikita.global
     */
    class Ngmailtelegram
    {

        /**
        Construct

        @return void
         **/
        public function __construct()
        {
            $this->prefix = 'Ngmailtelegram';
            $this->version = '1.0';
            $this->pluginName = __('NG WP Mail to telegram');
            $this->options = get_option($this->prefix);
            $this->telegramApi = 'https://api.telegram.org/bot{bottoken}/{method}';
            $this->settings = array(
                'bottoken' => array(
                    'key' => 'bottoken',
                    'title' => __('Bot token'),
                    'placeholder' => __('Bot token'),
                    'group' => 'bot settings',
                    'type' => 'text',
                    'required' => true,
                ),
                'botrest' => array(
                    'key' => 'botreset',
                    'title' => __('init webhooks for this site'),
                    'group' => 'bot settings',
                    'type' => 'checkbox',
                    'required' => false,
                ),
                'messagefilter' => array(
                    'key' => 'messagefilter',
                    'title' => __('Message filter'),
                    'group'=>'bot settings',
                    'type'=>'select',
                    'required'=>false,
                    'args'=>apply_filters($this->prefix.'messageFilters', array(
                        'none'=>__('Do not filter'),
                        'subject'=>__('Send subject only'),                        
                        'short'=>__('Cut long message'),
                        'stripTags'=>__('Strip html tags'),
                        'file'=>__('Send file')
                    ))
                )
            );
            foreach ($this->settings as $k=>$setting)
            {
                if (isset($this->options[$setting['key']]))
                {
                    $this->settings[$k]['value']=$this->options[$setting['key']];
                }
            }
            add_action('wp_enqueue_scripts', array($this, 'scripts'));
            add_action('admin_menu', array($this, 'menuItemAdd'));
            add_action('admin_init', array($this, 'settingsRegister'));
            add_action('plugins_loaded', array($this, 'processGetQuery'));
            add_action('init', array($this, 'initBot'));
            add_action('rest_api_init', array($this, 'initHook'));
            add_filter('wp_mail', array($this,'wpMail'));
            if (isset($this->options['messagefilter']) && method_exists($this,'messageFilter'.$this->options['messagefilter']))
            {
                add_filter($this->prefix.'message', array($this, 'messageFilter'.$this->options['messagefilter']));
            }
            if (isset($this->options['messagefilter']) && method_exists($this,'queryFilter'.$this->options['messagefilter'])){
                add_filter($this->prefix.'sendMessageParams', array($this, 'queryFilter'.$this->options['messagefilter']));
            }
        }

        /**
        Register settings

        @return void
         **/
        public function settingsRegister()
        {
            register_setting($this->prefix, $this->prefix);
            $groups = array();
            foreach ($this->settings as $settingname => $array) {
                if (!in_array($array['group'], $groups)) {
                    add_settings_section(
                        $this->prefix . $array['group'],
                        __($array['group'], $this->prefix),
                        array($this, 'sectionCallBack'),
                        $this->prefix
                    );
                    $this->groups[] = $array['group'];
                }
                add_settings_field(
                    $array['key'],
                    __($array['title'], $this->prefix),
                    array($this, 'makeField'),
                    $this->prefix,
                    $this->prefix . $array['group'],
                    $array
                );
            }
        }

        public function initBot()
        {
            if (!$this->options['bottoken']) {
                $this->hookUrl = false;
                return;
            }
            $this->hookUrl = get_rest_url(null, $this->prefix . '/v1/hook/' . $this->_protectString($this->options['bottoken']));
            $botsetkey = $this->prefix . 'botset' . crc32($this->options['bottoken']);
            if ($this->options['botreset'] == 1 && !get_option($botsetkey)) {
                $reply = ($this->_queryGet(array(
                    'method' => 'setwebhook',
                    'url' => $this->hookUrl,
                )));
                if ($reply['ok'] == 1) {
                    update_option($botsetkey, true);
                }
            }

        }

        public function wpMail($args){
            $to = $args['to'];
            $user = get_user_by( 'email', $to);
            $chatid=get_user_meta($user->ID, $this->prefix, true);
            if (!$chatid) {
                return $args;
            }
            $message=$args['message'];
            $subject=$args['subject'];
            $this->_queryPost(
                apply_filters($this->prefix.'sendMessageParams',
                array(
                    'method'=>'sendMessage',
                    'caption'=>$args['subject'],
                    'text'=>apply_filters($this->prefix.'message', $args),
                    'chat_id'=>$chatid,
                )
                )
            );
            return $args;
        }

        public function queryFilterFile($args){
            $file=get_temp_dir().crc32($args['caption']).'.html';            
            file_put_contents($file, $args['text']);
            $args['method']='sendDocument';
            $args['document']=new \CURLFile($file);
            return $args;
        }

        public function messageFilterSubject($args)
        {
            return $args['subject'];
        }

        public function messageFilterShort($args)
        {
            return preg_replace(
                '/^(.{300}[^\ ]*)\ [^\$]*$/s',
                '$1...',
                $args['message']
            );
        }

        public function messageFilterStripTags($args)
        {
            return preg_replace( "/\n\s+/", "\n", rtrim(html_entity_decode(strip_tags($args['message']))) );
        }

        public function messageFilterFile($args)
        {
            exec('/usr/bin/w3m1 -v', $out, $arr);
            var_dump($out);
            var_dump($arr);
            return $args['message'];
        }

        public function processGetQuery()
        {
            if (!isset($_GET[$this->prefix])) {
                return;
            }
            $data = $_GET[$this->prefix];
            $n = $data['data'] . $data['action'];
            if (!$this->_checkNonce($data['nonce'], $n)) {
                     wp_die(__('Broken link'));
            }
            $method = '_processGetQuery' . $data['action'];
            if (method_exists($this, $method)) {
                return $this->{$method}($data);
            }            
        }

        private function _processGetQuerySubscribe($data)
        {
            $current_user = wp_get_current_user();
            //var_dump($current_user->user_email);
            //var_dump($current_user->ID);
            update_user_meta($current_user->ID, $this->prefix, $data['data']);
            $this->_say($data['data'], __('Subscibed!'));
            die();
        }

        public function initHook()
        {
            if (!isset($this->options['bottoken'])) {
                return;
            }
            register_rest_route($this->prefix . '/v1', '/hook/' .
                $this->_protectString($this->options['bottoken']), array(
                    'methods' => 'POST',
                    'callback' => array($this, 'processHook'),
                )
            );
        }

        public function processHook($data)
        {
            $body = json_decode($data->get_body());
            $message = $body->message;
            if (
                isset($message->entities[0]->type)
                &&
                $message->entities[0]->type == 'bot_command') {
                return $this->_processBotCommand($message);
            }
            return new WP_REST_Response(array('ok' => true), 200);
        }

        private function _processBotCommand($message)
        {
            $command = preg_replace('/^\//', 'botCommand', $message->text);
            $method = '_process' . $command;
            if (method_exists($this, $method)) {
                return $this->{$method}($message);
            } else {
                return $this->_botReply(
                    $message,
                    $this->_text('Unknown command') . ' ' . $message->text
                );
            }
        }

        private function _processBotCommandStart($message)
        {
            return $this->_botReply($message,
                $this->_text('Hello. Please follow this link to subscribe:') . ' ' . $this->_makeLink($message)
            );
        }

        private function _makeLink($message)
        {
            return '['.get_site_url().']('.add_query_arg(
                array(
                    'page' => $this->prefix,
                    $this->prefix => array
                    (
                        'action' => 'subscribe',
                        'data' => $message->chat->id,
                        'nonce' => $this->_createNonce(
                            $message->chat->id .
                            'subscribe'
                        ),
                    ),
                ),
                admin_url() . 'options-general.php').')';
        }

        private function _botReply($message, $text)
        {
            $response = array(
                'method' => 'sendMessage',
                'chat_id' => $message->chat->id,
               // 'reply_to_message_id' => $message->message_id,
                'text' => $text,
                'parse_mode'=>'Markdown'
            );
            return new WP_REST_Response($response, 200);
        }

        private function _say($chatid, $text)
        {
return $this->_queryGet(array(
    'method'=>'sendMessage',
    'chat_id'=>$chatid,
    'text'=>$this->_text($text)
));
        }

        private function _text($text)
        {
            return __($text);
        }

        /**
        Options page in settings

        @return void
         **/
        public function menuItemAdd()
        {
            $this->optionsPage = add_options_page(
                $this->pluginName,
                $this->pluginName,
                'manage_options',
                $this->prefix,
                array($this, 'optionsPage')
            );
        }

        /**
        Backend options options page

        @return void
         **/
        public function optionsPage()
        {
            ?><form action='options.php' method='post'>
            <h2><?php echo $this->pluginName; ?></h2>
            <?php
            settings_fields($this->prefix);
            do_settings_sections($this->prefix);
            submit_button();
            ?></form><?php
            if (!isset($this->options['bottoken']) || !$this->options['bottoken']) {
                return;
            }            
            $this->_terminal(($this->_queryGet(array('method' => 'getMe'))), __('Testing bot'));
            //$this->_terminal(($this->_queryGet(array('method' => 'getWebhookInfo'))), __('Current webhooks'));
        }

        private function _queryGet($args)
        {
            $this->args = array_merge($args, array(
                'bottoken' => $this->options['bottoken'],
            ));
            $url = (
                preg_replace_callback(
                    '/\{([^\}]*)\}/',
                    function ($matches) {
                        $set = $this->args[$matches[1]];
                        unset($this->args[$matches[1]]);
                        return $set;
                    },
                    $this->telegramApi
                )
            );
            $url = add_query_arg($this->args, $url);
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $reply = curl_exec($curl);
            curl_close($curl);
            return json_decode($reply, true);
        }

        private function _queryPost($args){
            $this->args = array_merge($args, array(
                'bottoken' => $this->options['bottoken'],
            ));
            $url = (
                preg_replace_callback(
                    '/\{([^\}]*)\}/',
                    function ($matches) {
                        $set = $this->args[$matches[1]];
                        unset($this->args[$matches[1]]);
                        return $set;
                    },
                    $this->telegramApi
                )
            );
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: multipart/form-data"));
            curl_setopt($curl, CURLOPT_POSTFIELDS, $args);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_exec($curl);
            curl_close($curl);
        }

        private function _terminal($output, $title = false)
        {
            if ($title) {
                echo '<h2>' . $title . '</h2>';
            }
            echo '<XMP>';
            print_r($output);
            echo '</XMP>';
        }

        /**
        Settings field - default
         *
        @param array $args arguments

        @return void
         **/
        public function makeField($args)
        {
            $methodName = 'makeField' . $args['type'];
            if (method_exists($this, $methodName)) {
                return $this->{$methodName}($args);
            }
            echo '<input ';
            echo ' class="regular-text"';
            echo ' type="';
            echo $args['type'];
            echo '"';
            echo $this->_makeFieldAttr($args);
            echo ' value="';
            if (isset($args['value'])) {
                echo $args['value'];
            } else {
                if (isset($args['default'])) {
                    echo $args['default'];
                }
            }
            echo '"';
            echo '>';            
          //  echo $this->_makeFieldRequired($args) . '>';
        }

        /**
        Settings field - checkbox
         *
        @param array $args arguments

        @return void
         **/
        public function makeFieldCheckBox($args)
        {
            echo '<input type="checkbox" value="1"';
            echo $this->_makeFieldAttr($args);
            if (isset($this->options[$args['key']])
                && $this->options[$args['key']]
            ) {
                echo 'checked';
            }
            
            echo '>';
        }

        public function makeFieldSelect($args){
            echo '<select ';
            echo $this->_makeFieldAttr($args);            
            echo '>';
            echo $this->_makeFieldSelectOptions($args);
            echo '</select>';            
        }

        public function _makeFieldSelectOptions($args)
        {
            foreach ($args['args'] as $k=>$v)
            {
                echo '<option ';
                echo 'value="'.$k.'" ';
                if ($args['value']==$k) {
                   echo 'selected ';
                }
                echo ">"                ;
                echo $v;
                echo '</option>';
                
            }
        }

        /**
        Name and Required attribute for field
         *
        @param array $args arguments

        @return void
         **/
        private function _makeFieldAttr($args)
        {
            echo " name=\"";
            echo $this->prefix . '[';
            echo $args['key'] . ']" ';
            if (isset($args['placeholder'])) {
                echo ' placeholder="'.$args['placeholder'].'"';
            }
            if (isset($args['required']) && $args['required']) {
                echo ' required="required"';
            }
        }

        /**
        Output under sectionCallBack

        @return void
         **/
        public function sectionCallBack()
        {
            echo __('<hr>', $this->prefix);
        }

        /**
        Enqueue scripts

        @return void
         **/
        public function scripts()
        {
            wp_register_script(
                $this->prefix,
                plugin_dir_url(__FILE__) . '/plugin.js',
                array('jquery'),
                $this->version,
                true
            );
            wp_localize_script(
                $this->prefix,
                $this->prefix,
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                )
            );
            wp_enqueue_script($this->prefix);
        }

        private function _checkNonce($nonce, $action)
        {
            $key = $this->prefix . 'nonce' . crc32($action);
            $nonce = $this->_protectString($action . 'NONCE_SALT');
            if (get_transient($key) == $nonce) {
                delete_transient($key);
                return true;
            }
            return false;
        }

        private function _createNonce($action)
        {
            $key = $this->prefix . 'nonce' . crc32($action);
            $nonce = $this->_protectString($action . 'NONCE_SALT');
            set_transient($key, $nonce, 600);
            return $nonce;
        }

        private function _protectString($string)
        {
            return sha1($this->prefix . $string);
        }
    }
}
new Ngmailtelegram();
