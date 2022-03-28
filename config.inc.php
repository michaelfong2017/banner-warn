<?php

$config = array();

// Regex to match against the email to determine if from your organization
$config['org_email_regex'] = "/@(.*\.|)vanguard-email\.com/i";

// Turn on letter avatars
$config['avatars'] = true;

// Make external avatars hexagon
$config['avatars_external_hexagon'] = true;

// Header that marks the message as SPAM. ('Yes').
$config['x_spam_status_header'] = 'x-spam-status';

// Header to check spam level. Counts number of asterisk in this.
$config['x_spam_level_header'] = 'x-spam-level';

// Spam threshold for X-Spam-Level to alert user for
$config['spam_level_threshold'] = 4;

// Header that marks the message as SPF fail. ('Pass' to pass).
$config['received_spf_header'] = 'received-spf';

// Display images for avatars
// If you don't use images for avatars, set `false` to save performance
$config['avatar_images'] = true;

