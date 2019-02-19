<?php
/**
 * IPN processor for Shop notifications.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2018 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion functions */
require_once '../../lib-common.php';

if ($_SHOP_CONF['debug_ipn'] == 1) {
    // Get the complete IPN message prior to any processing
    COM_errorLog("Recieved IPN:", 1);
    COM_errorLog(var_export($_POST, true), 1);
}

// Process IPN request
$ipn = \Shop\IPN::getInstance('paypal', $_POST);
$ipn->Process();

// Finished (this isn't necessary...but heck...why not?)
echo "Thanks";

?>
