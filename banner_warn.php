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

        private $flags = array(
            'JUNK'    => 'Junk',
            'NONJUNK' => 'NonJunk'
        ); 

        function init()
        {
            $this->register_action('plugin.markasknown.known', [$this, 'mark_message']);
            $this->register_action('plugin.markasknown.unknown', [$this, 'mark_message']);
            $this->register_action('plugin.markasknown.report', [$this, 'report_message']);

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
                'label'      => 'As known sender',
                'id'         => 'markasknown',
                'class'      => 'icon known disabled',
                'classact'   => 'icon known',
                'innerclass' => 'icon known'
            ], 'markmenu');
            $this->add_button([
                'command'    => 'plugin.markasknown.unknown',
                'type'       => 'link-menuitem',
                'label'      => 'As unknown sender',
                'id'         => 'markasunknown',
                'class'      => 'icon unknown disabled',
                'classact'   => 'icon unknown',
                'innerclass' => 'icon unknown'
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

        public function storage_init($p)
        {
            $p['fetch_headers'] = trim($p['fetch_headers'] . ' ' . strtoupper($this->x_spam_status_header) . ' ' . strtoupper($this->x_spam_level_header). ' ' . strtoupper($this->received_spf_header));
            $p['message_flags'] = array_merge((array) $p['message_flags'], $this->flags);
            return $p;
        }

        public function warn($args)
        {
            // rcmail::console('warn');
            $view_variable = 'warn';
            // banner_warn::console_log($view_variable);

            $this->add_texts('localization/');

            // Preserve exiting headers
            $content = $args['content'];
            $message = $args['message'];

            // Safety check
            if ($message === NULL || $message->sender === NULL || $message->headers->others === NULL) {
                return array();
            }

            // Warn users if mail from outside organization
            $command = 'is-known-sender';
            $uid = $message->uid;
            $sender_address = $message->sender['mailto'];
            $mbox_name  = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_GET);

            // $RCMAIL = rcmail::get_instance();
            // $storage = $RCMAIL->get_storage();
            // $source_mbox = 'INBOX';
            // $flags = $storage->list_flags($source_mbox, [$uid]);
            
            $data = array("sender_address" => $sender_address);

            $output = $this->_make_request("POST", "localhost:8000/" . $command, $data);
            rcmail::console('$output: ' .print_r($output,1));

            // Create and display warning banner
            if (substr($output, 0, strlen('KNOWN')) !== 'KNOWN') { // case-sensitive
                if ($mbox_name !== 'Drafts' && $mbox_name !== 'Sent') {
                    if ($mbox_name === 'Junk' || $mbox_name === 'Trash') {
                        array_push($content, '<div class="notice warning reported" style="white-space: pre-wrap;">' . "Reported as junk!" . '</div>');
                    }
                    // INBOX only by default
                    else {
                        array_push($content, '<div class="notice warning" style="white-space: pre-wrap;">' . $sender_address . " originated from outside of your organization. Do not click links or open attachments unless you recognize the sender and know the content is safe.\n\nWould you like to recognize and trust " . $sender_address . "?"
                        . '<button class="yes-button" uid=' . $uid . ' sender=' . $sender_address . ' type="button">Yes</button>'
                        . '<button class="no-button" uid=' . $uid . ' sender=' . $sender_address . ' type="button">No</button>'
                        . '</div>');
                    }
                }
            }
            // Still create but don't display warning banner
            else {
                if ($mbox_name !== 'Drafts' && $mbox_name !== 'Sent') {
                    if ($mbox_name === 'Junk' || $mbox_name === 'Trash') {
                        array_push($content, '<div class="notice warning reported" style="white-space: pre-wrap;">' . "Reported as junk!" . '</div>');
                    }
                    // INBOX only by default
                    else {
                        array_push($content, '<div class="notice warning" style="white-space: pre-wrap; display: none;">' . $sender_address . " originated from outside of your organization. Do not click links or open attachments unless you recognize the sender and know the content is safe.\n\nWould you like to recognize and trust " . $sender_address . "?"
                        . '<button class="yes-button" uid=' . $uid . ' sender=' . $sender_address . ' type="button">Yes</button>'
                        . '<button class="no-button" uid=' . $uid . ' sender=' . $sender_address . ' type="button">No</button>'
                        . '</div>');
                    }
                }
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
                    // rcmail::console('$from[\'mailto\']: ' . print_r($from['mailto'], 1));
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
            $RCMAIL = rcmail::get_instance();

            $this->add_texts('localization');

            $uids = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
            $senders = rcube_utils::get_input_value('_senders', rcube_utils::INPUT_POST);
            // rcmail::console('$senders: ' . print_r($senders,1));
            $mbox_name  = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
            $messageset = rcmail::get_uids($uids, $mbox_name, $multifolder);
            // rcmail::console('$uids: ' . print_r($uids,1));

            // rcmail::console('$RCMAIL->action: ' . print_r($RCMAIL->action,1));

            $is_known = $RCMAIL->action == 'plugin.markasknown.known';

            // rcmail::console('$is_known: ' . print_r($is_known,1));

            $is_known ? $this->_known($messageset, $senders) : $this->_unknown($messageset, $senders);

            if (!is_null($is_known)) {
                /* Display message */
                $display_message = 'Sender(s) address marked as ' . ($is_known ? 'known' : 'unknown') . ' successfully.';
                $RCMAIL->output->command('display_message', $RCMAIL->gettext($display_message));
            }

            /* Update the content part immediately */
            $RCMAIL->output->command('hide_warning', $is_known);

            // Must be put at the last line
            $RCMAIL->output->send();
        }

        private function _known(&$messageset, &$senders) {
            // rcmail::console('_known');
            $RCMAIL = rcmail::get_instance();

            foreach ($messageset as $source_mbox => &$uids) {
                // rcmail::console('$source_mbox: ' .print_r($source_mbox,1));

                $command = 'set-known-sender';
                $sender_addresses = implode(";", $senders);
                
                $data = array("sender_addresses" => $sender_addresses);

                $output = $this->_make_request("POST", "localhost:8000/" . $command, $data);
                rcmail::console('$output: ' .print_r($output,1));
            }
        }
        private function _unknown(&$messageset, &$senders) {
            // rcmail::console('_unknown');
            $RCMAIL = rcmail::get_instance();

            foreach ($messageset as $source_mbox => &$uids) {
                // rcmail::console('$source_mbox: ' .print_r($source_mbox,1));

                $command = 'set-unknown-sender';
                $sender_addresses = implode(";", $senders);
                
                $data = array("sender_addresses" => $sender_addresses);

                $output = $this->_make_request("POST", "localhost:8000/" . $command, $data);
                rcmail::console('$output: ' .print_r($output,1));
            }
        }

        public function report_message() {
            // rcmail::console('report_message');
            $RCMAIL = rcmail::get_instance();

            $uid = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
            $sender = rcube_utils::get_input_value('_sender', rcube_utils::INPUT_POST);
            // rcmail::console('$uid: ' . print_r($uid,1));
            // rcmail::console('$sender: ' . print_r($sender,1));
            $mbox_name  = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
            $messageset = rcmail::get_uids($uids, $mbox_name, $multifolder);

            /* Set flag */
            $storage = $RCMAIL->get_storage();

            foreach ($messageset as $source_mbox => &$uids) {
                $storage->set_folder($source_mbox);

                if (array_key_exists('JUNK', $this->flags)) {
                    $storage->set_flag($uid, 'JUNK', $source_mbox);
                }
            }

            /* Display message */
            $display_message = 'Moving message to Junk box...';
            $RCMAIL->output->command('display_message', $RCMAIL->gettext($display_message));

            /* Move to the junk box */
            $dest_mbox = 'Junk';

            if ($dest_mbox && ($mbox_name !== $dest_mbox || $multifolder)) {
                $RCMAIL->output->command('rcmail_markasjunk2_move', $dest_mbox, $this->_messageset_to_uids($messageset, $multifolder));
            }
            else {
                $RCMAIL->output->command('command', 'list', $mbox_name);
            }
        }
        private function _messageset_to_uids($messageset, $multifolder) {
            $a_uids = array();

            foreach ($messageset as $mbox => $uids) {
                foreach ($uids as $uid) {
                    $a_uids[] = $multifolder ? $uid . '-' . $mbox : $uid;
                }
            }

            return $a_uids;
        }

        private function _make_request($method, $url, $data = false)
        {
            $curl = curl_init();

            switch ($method)
            {
                case "POST":
                    curl_setopt($curl, CURLOPT_POST, 1);

                    if ($data)
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data'));
                    break;
                case "PUT":
                    curl_setopt($curl, CURLOPT_PUT, 1);
                    break;
                default:
                    if ($data)
                        $url = sprintf("%s?%s", $url, http_build_query($data));
            }

            // Optional Authentication:
            // curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            // curl_setopt($curl, CURLOPT_USERPWD, "username:password");

            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

            $result = curl_exec($curl);

            curl_close($curl);

            return $result;
        }
    }

