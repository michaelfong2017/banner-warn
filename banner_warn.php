<?php

    /**
     * Banner Warn
     *
     * Displays warnings to the user under various contexts
     *
     * @license MIT License: <http://opensource.org/licenses/MIT>
     * @author Varun Patil
     * @category  Plugin for RoundCube WebMail
     */
    class banner_warn extends rcube_plugin
    {
        public $task = 'mail';

        private $org_mail_regex;
        private $x_spam_status_header;
        private $x_spam_level_header;
        private $received_spf_header;
        private $spam_level_threshold;
        private $avatar_images;

        function init()
        {
            $this->register_action('plugin.markasknown.known', [$this, 'mark_message']);
            $this->register_action('plugin.markasknown.not_known', [$this, 'mark_message']);

            $this->load_config('config.inc.php.dist');
            $this->load_config('config.inc.php');

            $this->include_script('banner_warn.js');
            $this->include_stylesheet('banner_warn.css');

            $this->add_hook('storage_init', array($this, 'storage_init'));
            $this->add_hook('message_objects', array($this, 'warn'));
            $this->add_hook('messages_list', array($this, 'mlist'));

            // Get RCMAIL object
            $RCMAIL = rcmail::get_instance();

            // Get config
            $this->org_mail_regex = $RCMAIL->config->get('org_email_regex');
            $this->x_spam_status_header = $RCMAIL->config->get('x_spam_status_header');
            $this->x_spam_level_header = $RCMAIL->config->get('x_spam_level_header');
            $this->received_spf_header = $RCMAIL->config->get('received_spf_header');
            $this->spam_level_threshold = $RCMAIL->config->get('spam_level_threshold');
            $this->avatar_images = $RCMAIL->config->get('avatar_images');

            // add the buttons to the mark message menu
            $this->add_button([
                'command'    => 'plugin.markasknown.known',
                'type'       => 'link-menuitem',
                'label'      => 'markasknown.asknown',
                'id'         => 'markasknown',
                'class'      => 'icon known disabled',
                'classact'   => 'icon known active',
                'innerclass' => 'icon known'
            ], 'markmenu');
            $this->add_button([
                'command'    => 'plugin.markasknown.not_known',
                'type'       => 'link-menuitem',
                'label'      => 'markasknown.asnotknown',
                'id'         => 'markasnotknown',
                'class'      => 'icon notknown disabled',
                'classact'   => 'icon notknown active',
                'innerclass' => 'icon notknown'
            ], 'markmenu');
        }

        public static function console_log($output, $with_script_tags = true) {
            $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) . 
        ');';
            if ($with_script_tags) {
                $js_code = '<script>' . $js_code . '</script>';
            }
            echo $js_code;
        }

        public static function write_log($log_msg) {
            $log_filename = "logs";
            if (!file_exists($log_filename))
            {
                mkdir($log_filename, 0777, true);
            }
            $log_file_data = $log_filename.'/debug.log';
            file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
        }

        public function storage_init($p)
        {
            $p['fetch_headers'] = trim($p['fetch_headers'] . ' ' . strtoupper($this->x_spam_status_header) . ' ' . strtoupper($this->x_spam_level_header). ' ' . strtoupper($this->received_spf_header));
            return $p;
        }

        public function warn($args)
        {
            rcmail::console('hihi');

            $view_variable = 'warn';
            banner_warn::console_log($view_variable);
            banner_warn::write_log("Writing Log");
            $a = array(
                array('id' => '1','date' => '09-04-2018','length' => '10'),
                array('id' => '2','date' => '09-04-2018','length' => '20'),
                array('id' => '1','date' => '10-04-2018','length' => '11')
            );
            rcmail::console(print_r($a,1));

            $this->add_texts('localization/');

            // Preserve exiting headers
            $content = $args['content'];
            $message = $args['message'];

            // Safety check
            if ($message === NULL || $message->sender === NULL || $message->headers->others === NULL) {
                return array();
            }

            // Warn users if mail from outside organization
            if ($this->addressExternal($message->sender['mailto'])) {
                array_push($content, '<div class="notice warning">' . $this->gettext('from_outsite') . '</div>');
            }

            // Check X-Spam-Status
            if ($this->isSpam($message->headers)) {
                array_push($content, '<div class="notice error">' . $this->gettext('posible_spam') . '</div>');
            }

            // Check Received-SPF
            if ($this->spfFails($message->headers)) {
                array_push($content, '<div class="notice error">' . $this->gettext('spf_fail') . '</div>');
            }

            return array('content' => $content);
        }

        public function mlist($p)
        {
            if (!empty($p['messages'])) {
                $RCMAIL = rcmail::get_instance();

                // Check if avatars disabled
                if (!$RCMAIL->config->get('avatars', true)) return;

                $banner_avatar = array();
                foreach ($p['messages'] as $index => $message) {
                    // Create entry
                    $banner_avatar[$message->uid] = array();

                    // Parse from address
                    $from = rcube_mime::decode_address_list($message->from, 1, true, null, false)[1];

                    // Check if we have a from email address (uhh)
                    if (isset($from)) {
                        // Get first letter of name
                        $name = $from["name"];
                        if (empty($name)) {
                            $name = $from["mailto"];
                        }
                        $name = preg_replace("/[^A-Za-z0-9 ]/", '', $name);
                        $name = strtoupper($name[0]);

                        // Get md5 color from email
                        $color = substr(md5($from["mailto"]), 0, 6);
                    }

                    // Check for SPF fail
                    if (!isset($from) || $this->isSpam($message) || $this->spfFails($message)) {
                        $color = 'ff0000';
                        $name = '!';
                        $banner_avatar[$message->uid]['alert'] = 1;
                    }
                    else if ($RCMAIL->config->get('avatars_external_hexagon', true) && $this->addressExternal($from["mailto"])) {
                        $banner_avatar[$message->uid]['warn'] = 1;
                    }

                    $banner_avatar[$message->uid]['name'] = $name;
                    $banner_avatar[$message->uid]['from'] = $from['mailto'];
                    banner_warn::write_log(print_r($from['mailto'], 1));
                    $banner_avatar[$message->uid]['color'] = $color;
                }

                $RCMAIL->output->set_env('banner_avatar', $banner_avatar);
                $RCMAIL->output->set_env('banner_avatar_images', $this->avatar_images);
            }

            return $p;
        }

        private function first($obj) {
            return (isset($obj) && is_array($obj)) ? $obj[0] : $obj;
        }

        private function addressExternal($address) {
            return (!preg_match($this->org_mail_regex, $address));
        }

        private function spfFails($headers) {
            $spfStatus = $this->first($headers->others[strtolower($this->received_spf_header)]);
            return (isset($spfStatus) && (strpos(strtolower($spfStatus), 'pass') !== 0));
        }

        private function isSpam($headers) {
            $spamStatus = $this->first($headers->others[strtolower($this->x_spam_status_header)]);
            if (isset($spamStatus) && (strpos(strtolower($spamStatus), 'yes') === 0)) return true;

            $spamLevel = $this->first($headers->others[strtolower($this->x_spam_level_header)]);
            return (isset($spamLevel) && substr_count($spamLevel, '*') >= $this->spam_level_threshold);
        }

        /* functions for UI button in markmenu */
        public function mark_message() {
            banner_warn::write_log('mark_message');

            $RCMAIL = rcmail::get_instance();

            $this->add_texts('localization');

            $uids = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
            banner_warn::write_log(print_r($uids,1));

            $is_known = $RCMAIL->action == 'plugin.markasknown.known';

            banner_warn::write_log(print_r($is_known,1));

            if ($is_known) {
                $RCMAIL->output->command('display_message', $RCMAIL->gettext($is_known ? 'markedasknown' : 'markedasnotknown'));
            }

            $RCMAIL->output->send();
        }
    }

