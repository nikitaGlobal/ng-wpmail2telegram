<?php
    
    /**
     * Plugin Name: ng-mail2telegram
     * Plugin URI: http://nikita.global
     * Description: decription
     * Author: Nikita Menshutin
     * Version: 1.0
     * Author URI: http://nikita.global
     *
     * PHP version 7.2
     *
     * @category NikitaGlobal
     * @package  NikitaGlobal
     * @author   Nikita Menshutin <nikita@nikita.global>
     * @license  http://nikita.global commercial
     * @link     http://nikita.global
     * */
    defined('ABSPATH') or die("No script kiddies please!");
    if (! class_exists("Ngmailtelegram")) {
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
             * Construct
             *
             * @return void
             **/
            public function __construct()
            {
                $this->prefix      = 'Ngmailtelegram';
                $this->version     = '1.0';
                $this->pluginName  = __('NG WP Mail to telegram', $this->prefix);
                $this->options     = get_option($this->prefix);
                $this->telegramApi = 'https://api.telegram.org/bot{bottoken}/{method}';
                $this->settings    = array(
                    'bottoken'      => array(
                        'key'         => 'bottoken',
                        'title'       => __('Bot token', $this->prefix),
                        'placeholder' => __('Bot token', $this->prefix),
                        'group'       => 'bot settings',
                        'type'        => 'text',
                        'required'    => true,
                    ),
                    'botrest'       => array(
                        'key'      => 'botreset',
                        'title'    => __('init webhooks for this site', $this->prefix),
                        'group'    => 'bot settings',
                        'type'     => 'checkbox',
                        'required' => false,
                    ),
                    'messagefilter' => array(
                        'key'      => 'messagefilter',
                        'title'    => __('Message filter', $this->prefix),
                        'group'    => 'bot settings',
                        'type'     => 'select',
                        'required' => false,
                        'args'     => apply_filters(
                            $this->prefix . 'messageFilters', array(
                                'none'      => __('Do not filter', $this->prefix),
                                'subject'   => __('Send subject only', $this->prefix),
                                'short'     => __('Cut long message', $this->prefix),
                                'stripTags' => __('Strip html tags', $this->prefix),
                                'file'      => __('Send file', $this->prefix)
                            )
                        )
                    )
                );
                foreach ($this->settings as $k => $setting) {
                    if (isset($this->options[$setting['key']])) {
                        $this->settings[$k]['value'] = $this->options[$setting['key']];
                    }
                }
                add_action('admin_menu', array($this, 'menuItemAdd'));
                add_action('admin_init', array($this, 'settingsRegister'));
                add_action('plugins_loaded', array($this, 'processGetQuery'));
                add_action('init', array($this, 'initBot'));
                add_action('rest_api_init', array($this, 'initHook'));
                add_filter('wp_mail', array($this, 'wpMail'));
                add_action('init', array($this,'initStrings'));
                $this->strings=array(
                    'unknown_command'=>'Unknown command',
                    'hello'=>'Hello. Please follow this link to subscribe:'
                );
                if (isset($this->options['messagefilter'])
                    && method_exists(
                        $this,
                        'messageFilter' .
                        $this->options['messagefilter']
                    )
                ) {
                    add_filter(
                        $this->prefix .
                        'message',
                        array(
                            $this,
                            'messageFilter' .
                            $this->options['messagefilter']
                        )
                    );
                }
                if (isset($this->options['messagefilter'])
                    && method_exists(
                        $this,
                        'queryFilter' .
                        $this->options['messagefilter']
                    )
                ) {
                    add_filter(
                        $this->prefix .
                        'sendMessageParams',
                        array(
                            $this,
                            'queryFilter' .
                            $this->options['messagefilter']
                        )
                    );
                }
            }
            
            /**
             * Register settings
             *
             * @return void
             **/
            public function settingsRegister()
            {
                register_setting($this->prefix, $this->prefix);
                $groups = array();
                foreach ($this->settings as $settingname => $array) {
                    if (! in_array($array['group'], $groups)) {
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
            
            /**
             * Initting our bot
             * Setting webhooks if not set
             *
             * @return void
             */
            public function initBot()
            {
                if (! $this->options['bottoken']) {
                    $this->hookUrl = false;
                    
                    return;
                }
                $this->hookUrl = get_rest_url(
                    null,
                    $this->prefix .
                    '/v1/hook/' .
                    $this->_protectString(
                        $this->options['bottoken']
                    )
                );
                $botsetkey     = $this->prefix .
                                 'botset' .
                                 crc32(
                                     $this->options['bottoken']
                                 );
                if ($this->options['botreset'] == 1 && ! get_option($botsetkey)) {
                    $reply = ($this->_queryGet(
                        array(
                            'method' => 'setwebhook',
                            'url'    => $this->hookUrl,
                        )
                    ));
                    if ($reply['ok'] == 1) {
                        update_option($botsetkey, true);
                    }
                }
                
            }
            
            /**
             * WP Mail filter
             * Taking values, checking if user subscribed
             *
             * @param array $args wpmail array
             *
             * @return array the same
             */
            public function wpMail($args)
            {
                $to     = $args['to'];
                $user   = get_user_by('email', $to);
                $chatid = get_user_meta($user->ID, $this->prefix, true);
                if (! $chatid) {
                    return $args;
                }
                $this->_queryPost(
                    apply_filters(
                        $this->prefix . 'sendMessageParams',
                        array(
                            'method'  => 'sendMessage',
                            'caption' => $args['subject'],
                            'text'    => apply_filters($this->prefix . 'message', $args),
                            'chat_id' => $chatid,
                        )
                    )
                );
                return $args;
            }
            
            /**
             * API Query filter for file filter
             *
             * @param array $args args
             *
             * @return array args
             */
            public function queryFilterFile($args)
            {
                $file = get_temp_dir() . crc32($args['caption']) . '.html';
                file_put_contents($file, $args['text']);
                $args['method']   = 'sendDocument';
                $args['document'] = new CURLFile($file);
                
                return $args;
            }
            
            /**
             * Message filter - send only subject
             *
             * @param array $args args
             *
             * @return string subject
             */
            public function messageFilterSubject($args)
            {
                return $args['subject'];
            }
            
            /**
             * Message filter - short content
             *
             * @param array $args args
             *
             * @return string message
             */
            public function messageFilterShort($args)
            {
                return preg_replace(
                    '/^(.{300}[^\ ]*)\ [^\$]*$/s',
                    '$1...',
                    $args['message']
                );
            }
            
            /**
             * Message filter - strip tags
             *
             * @param object $args args
             *
             * @return string message
             */
            public function messageFilterStripTags($args)
            {
                return preg_replace(
                    "/\n\s+/",
                    "\n",
                    rtrim(
                        html_entity_decode(
                            strip_tags(
                                $args['message']
                            )
                        )
                    )
                );
            }
            
            /**
             * Message filter - send file
             *
             * @param object $args args
             *
             * @return string message
             */
            public function messageFilterFile($args)
            {
                return $args['message'];
            }
            
            /**
             * Processing get query
             *
             * @return bool
             */
            public function processGetQuery()
            {
                if (! isset($_GET[$this->prefix])) {
                    return false;
                }
                $data = $_GET[$this->prefix];
                $n    = $data['data'] . $data['action'];
                if (! $this->_checkNonce($data['nonce'], $n)) {
                    wp_die(__('Broken link', $this->prefix));
                }
                $method = '_processGetQuery' . $data['action'];
                if (method_exists($this, $method)) {
                    return $this->{$method}($data);
                }
                
                return false;
            }
            
            /**
             * Processing query subscribe to notifications
             *
             * @param object $data received data
             *
             * @return void
             */
            private function _processGetQuerySubscribe($data)
            {
                $current_user = wp_get_current_user();
                update_user_meta($current_user->ID, $this->prefix, $data['data']);
                $this->_say($data['data'], __($this->strings['subscribed'] ));
                wp_die(__($this->strings['subscribed'], $this->prefix));
            }
            
            /**
             * Register routes for webhooks
             *
             * @return void
             */
            public function initHook()
            {
                if (! isset($this->options['bottoken'])) {
                    return;
                }
                register_rest_route(
                    $this->prefix .
                    '/v1',
                    '/hook/' .
                    $this->_protectString($this->options['bottoken']), array(
                        'methods'  => 'POST',
                        'callback' => array($this, 'processHook'),
                    )
                );
            }
            
            /**
             * Processing data posted by telegram
             *
             * @param object $data in request
             *
             * @return WP_REST_Response
             */
            public function processHook($data)
            {
                $body    = json_decode($data->get_body());
                $message = $body->message;
                if (isset($message->entities[0]->type)
                    && $message->entities[0]->type == 'bot_command'
                ) {
                    return $this->_processBotCommand($message);
                }
                
                return new WP_REST_Response(array('ok' => true), 200);
            }
            
            /**
             * Processing command given to bot
             * Calling submethod if have applicable
             * or sending error to user
             *
             * @param object $message message from user to bot
             *
             * @return WP_REST_Response
             */
            private function _processBotCommand($message)
            {
                $command = preg_replace('/^\//', 'botCommand', $message->text);
                $method  = '_process' . $command;
                //$this->lang=$message->from->language_code; @todo later
                if (method_exists($this, $method)) {
                    return $this->{$method}($message);
                } else {
                    return $this->_botReply(
                        $message,
                        $this->__(
                            $this->strings['unknown_command'],
                            $message->from->language_code
                        ) . ' ' . $message->text
                    );
                }
            }
            
            /**
             * Processing command /start
             * Making subscription link
             * and sending to user
             *
             * @param object $message message from user to bot
             *
             * @return WP_REST_Response
             */
            private function _processBotCommandStart($message)
            {
                return $this->_botReply(
                    $message,
                    $this->__(
                        $this->strings['hello'],
                        $message->from->language_code
                    )
                    . ' '
                    . $this->_makeLink($message)
                );
            }
            
            /**
             * Making subscription link here
             *
             * @param object $message from user
             *
             * @return string subscription link
             */
            private function _makeLink($message)
            {
                return '[' . get_site_url() . '](' . add_query_arg(
                        array(
                            'page'        => $this->prefix,
                            $this->prefix => array
                            (
                                'action' => 'subscribe',
                                'data'   => $message->chat->id,
                                'nonce'  => $this->_createNonce(
                                    $message->chat->id .
                                    'subscribe'
                                ),
                            ),
                        ),
                        admin_url() . 'options-general.php'
                    ) . ')';
            }
            
            /**
             * Sending reply to user
             *
             * @param object $message from user
             * @param string $text    text to reply
             *
             * @return WP_REST_Response our reply
             */
            private function _botReply($message, $text)
            {
                $response = array(
                    'method'              => 'sendMessage',
                    'chat_id'             => $message->chat->id,
                    'reply_to_message_id' => $message->message_id,
                    'text'                => $text,
                    'parse_mode'          => 'Markdown'
                );
                
                return new WP_REST_Response($response, 200);
            }
            
            /**
             * Say something to user
             *
             * @param int    $chatid chat id
             * @param string $text   to reply
             *
             * @return mixed depends on submethod
             */
            private function _say($chatid, $text)
            {
                return $this->_queryGet(
                    array(
                        'method'  => 'sendMessage',
                        'chat_id' => $chatid,
                        'text'    => $text
                    )
                );
            }
            
            /**
             * Options page in settings
             *
             * @return void
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
             * Backend options options page
             *
             * @return void
             **/
            public function optionsPage()
            {
                echo '<form action="options.php" method="post">';
                echo '<h2>' . $this->pluginName . '</h2>';
                settings_fields($this->prefix);
                do_settings_sections($this->prefix);
                submit_button();
                echo '</form>';
                if (!isset($this->options['bottoken'])
                    || ! $this->options['bottoken']
                ) {
                    return;
                }
                $this->_terminal(
                    (
                    $this->_queryGet(
                        array(
                            'method' => 'getMe'
                        )
                    )
                    ),
                    __('Testing bot', $this->prefix)
                );
            }
            
            /**
             * Send get query method to Telegram API
             *
             * @param array $args args
             *
             * @return array reply
             */
            private function _queryGet($args)
            {
                $this->args = array_merge(
                    $args, array(
                        'bottoken' => $this->options['bottoken'],
                    )
                );
                $url        = (
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
                $url        = add_query_arg($this->args, $url);
                $curl       = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                $reply = curl_exec($curl);
                curl_close($curl);
                
                return json_decode($reply, true);
            }
            
            /**
             * Send get query method to Telegram API
             *
             * @param array $args args
             *
             * @return array reply
             */
            private function _queryPost($args)
            {
                $this->args = array_merge(
                    $args, array(
                        'bottoken' => $this->options['bottoken'],
                    )
                );
                $url        = (
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
                $curl       = curl_init();
                curl_setopt(
                    $curl, CURLOPT_HTTPHEADER,
                    array(
                        "Content-type: multipart/form-data"
                    )
                );
                curl_setopt($curl, CURLOPT_POSTFIELDS, $args);
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_exec($curl);
                curl_close($curl);
            }
            
            /**
             * Formatted output (currently not very nice
             *
             * @param mixed       $output output
             * @param bool|string $title  title
             *
             * @return void
             */
            private function _terminal($output, $title = false)
            {
                if ($title) {
                    echo '<h2>' . $title . '</h2>';
                }
                echo '<span style="white-space: pre;">';
                print_r($output);
                echo '</span>';
            }
            
            /**
             * Settings field - default
             *
             * @param array $args arguments
             *
             * @return void
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
            }
            
            /**
             * Settings field - checkbox
             *
             * @param array $args arguments
             *
             * @return void
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
            
            /**
             * Settings field select
             *
             * @param array $args settings arguments
             *
             * @return void
             */
            public function makeFieldSelect($args)
            {
                echo '<select ';
                echo $this->_makeFieldAttr($args);
                echo '>';
                echo $this->_makeFieldSelectOptions($args);
                echo '</select>';
            }
            
            /**
             * Settings field select - options tags
             *
             * @param array $args settings arguments
             *
             * @return void
             */
            private function _makeFieldSelectOptions($args)
            {
                foreach ($args['args'] as $k => $v) {
                    echo '<option ';
                    echo 'value="' . $k . '" ';
                    if ($args['value'] == $k) {
                        echo 'selected ';
                    }
                    echo ">";
                    echo $v;
                    echo '</option>';
                    
                }
            }
            
            /**
             * Name and Required attribute for field
             *
             * @param array $args arguments
             *
             * @return void
             **/
            private function _makeFieldAttr($args)
            {
                echo " name=\"";
                echo $this->prefix . '[';
                echo $args['key'] . ']" ';
                if (isset($args['placeholder'])) {
                    echo ' placeholder="' . $args['placeholder'] . '"';
                }
                if (isset($args['required']) && $args['required']) {
                    echo ' required="required"';
                }
            }
            
            /**
             * Output under sectionCallBack
             *
             * @return void
             **/
            public function sectionCallBack()
            {
                echo __('<hr>', $this->prefix);
            }
            
            /**
             * Checking nonce
             *
             * @param string $nonce  nonce
             * @param string $action action
             *
             * @return bool valid or not
             */
            private function _checkNonce($nonce, $action)
            {
                $key = $this->prefix . 'nonce' . crc32($action);
                if ($nonce != $this->_protectString(
                        $action . 'NONCE_SALT'
                    )
                ) {
                    return false;
                }
                if (get_transient($key) == $nonce) {
                    delete_transient($key);
                    
                    return true;
                }
                
                return false;
            }
            
            /**
             * Creating nonce.
             * Standard nonce cannot be used as is limited by same session_id
             *
             * @param string $action action
             *
             * @return string nonce
             */
            private function _createNonce($action)
            {
                $key   = $this->prefix . 'nonce' . crc32($action);
                $nonce = $this->_protectString($action . 'NONCE_SALT');
                set_transient($key, $nonce, 600);
                return $nonce;
            }
            
            public function initStrings(){
                $key=$this->prefix.(crc32(
                        serialize(
                            array(
                                get_available_languages(),
                                $this->strings
                            )
                        )
                    )
                    );
                if (get_transient($key)) {
                    return;
                }
                global $locale;
                $currentLocale=get_locale();
                foreach (get_available_languages() as $lang)
                {
                    $shortslug=preg_replace('/\_[^\$]*$/', '', $lang);
                    $locale=$lang;
                    foreach ($this->strings as $text){
                        update_option($this->prefix.crc32($text.$shortslug),
                            __($text, $this->prefix).$lang
                        );
                    }
                }
                $locale=$currentLocale;
                set_transient($key,600);
            }
            
            public function __($text,$lang='ru')
            {
                $translate=get_option($this->prefix.crc32($text.$lang));
                if ($translate){
                    return $translate;
                }
                return $text;
            }
            
            /**
             * Encode string with salt
             *
             * @param string $string to encode
             *
             * @return string encoded value
             */
            private function _protectString($string)
            {
                return sha1($this->prefix . $string);
            }
        }
    }
    new Ngmailtelegram();
