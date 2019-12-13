<?php
/*
Plugin Name:  What The Cron
Description:  What is the deal with my cron?
Author:       Gilbert Pellegrom
Version:      0.1.1
Requires PHP: 7.0
Requires WP:  5.3
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  what-the-cron

What The Cron is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

What The Cron is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with What The Cron. If not, see https://opensource.org/licenses/GPL-2.0.
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * The main SpinupWP function.
 *
 * @return \Gilbitron\WhatTheCron\Plugin
 */
function WhatTheCron()
{
    if (isset($GLOBALS['what_the_cron']) && $GLOBALS['what_the_cron'] instanceof \Gilbitron\WhatTheCron\Plugin) {
        return $GLOBALS['what_the_cron'];
    }

    $GLOBALS['what_the_cron'] = new \Gilbitron\WhatTheCron\Plugin(__FILE__);
    $GLOBALS['what_the_cron']->run();

    return $GLOBALS['what_the_cron'];
}

WhatTheCron();
