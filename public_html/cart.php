<?php
/**
 * Access to shopping cart - view, update, delete, etc.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner
 * @package     shop
 * @varsion     v1.2.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Require core glFusion code */
require_once '../lib-common.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (
    !isset($_SHOP_CONF) ||
    !in_array($_SHOP_CONF['pi_name'], $_PLUGINS) ||
    (!$_SHOP_CONF['anon_buy'] && COM_isAnonUser()) ||
    !SHOP_access_check()
) {
    COM_404();
    exit;
}

$content = '';
$action = '';
$actionval = '';
$view = '';
$expected = array(
    // Actions
    'update', 'checkout', 'savebillto', 'saveshipto', 'delete', 'nextstep',
    'empty',
    // Views
    'editcart', 'cancel', 'view',
);
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $actionval = $_POST[$provided];
        break;
    } elseif (isset($_GET[$provided])) {
        $action = $provided;
        $actionval = $_GET[$provided];
        break;
    }
}

//echo $action;die;
if ($action == '') {
    // Not defined in $_POST or $_GET
    // Retrieve and sanitize input variables.  Typically _GET, but may be _POSTed.
    COM_setArgNames(array('action', 'id', 'token'));
    $action = COM_getArgument('action');
}
if ($action == '') {
    // Still no defined action, set to "view"
    $action = 'view';
}

switch ($action) {
case 'update':
    Shop\Cart::getInstance()->Update($_POST);
    COM_refresh(SHOP_URL . '/cart.php');
    break;

case 'delete':
    // Delete a single item from the cart
    $id = COM_getArgument('id');
    \Shop\Cart::getInstance()->Remove($id);
    COM_refresh(SHOP_URL . '/cart.php');
    break;

case 'empty':
    // Remove all items from the cart
    \Shop\Cart::getInstance()->Clear();
    COM_setMsg($LANG_SHOP['cart_empty']);
    echo COM_refresh(SHOP_URL . '/index.php');
    break;

case 'checkout':
    // Set the gift card amount first as it will be overridden
    // if the _coupon gateway is selected
    $Cart = \Shop\Cart::getInstance();
    $Cart->validateDiscountCode();

    // Validate the cart items
    $invalid = $Cart->updateItems();
    if (!empty($invalid)) {
        COM_refresh(SHOP_URL . '/cart.php');
    }

    // Check that the cart has items. Could be empty if the session or login
    // has expired.
    if (!$Cart->hasItems()) {
        COM_setMsg($LANG_SHOP['cart_empty']);
        COM_refresh(SHOP_URL . '/index.php');
        exit;
    }

    $gateway = SHOP_getVar($_POST, 'gateway');
    if ($gateway !== '') {
        \Shop\Gateway::setSelected($gateway);
        $Cart->setGateway($gateway);
        Shop\Customer::getInstance($cart->uid)
            ->setPrefGW($gateway)
            ->saveUser();
    }
    if (isset($_POST['by_gc'])) {
        // Has some amount paid by coupon
        $Cart->setGC($_POST['by_gc']);
    } elseif ($gateway == '_coupon') {
        // Entire order is paid by coupon
        $Cart->setGC(-1);
    } else {
        // No amount paid by coupon
        $Cart->setGC(0);
    }

    if (isset($_POST['order_instr'])) {
        $Cart->instructions = $_POST['order_instr'];
    }
    if (isset($_POST['payer_email']) && !empty($_POST['payer_email'])) {
        $Cart->setEmail($_POST['payer_email']);
    }
    if (isset($_POST['shipper_id'])) {
        $Cart->setShipper($_POST['shipper_id']);
    }

    // Final check that all items are valid. No return or error message
    // unless this is the only issue. This is the final step after viewing
    // the cart so there shouldn't be any changes.
    //$invalid = $Cart->updateItems();
    if (empty($invalid)) {
        // Validate that all order fields are filled out. If not, then that is a
        // valid error and the error messages will be displayed upon return.
        $errors = $Cart->Validate();
        if (!empty($errors)) {
            COM_refresh(SHOP_URL . '/cart.php');
        }
    }

    if (isset($_POST['quantity'])) {
        // Update the cart quantities if coming from the cart view.
        // This also calls Save() on the cart
        $Cart->Update($_POST);
    } else {
        $Cart->Save();
    }
    // See what workflow elements we already have.
    $next_step = SHOP_getVar($_POST, 'next_step', 'integer', 0);
    if ($_SHOP_CONF['anon_buy'] == 1 || !COM_isAnonUser()) {
        $view = 'none';
        $content .= $Cart->getView($next_step);
        break;
    } else {
        $content .= SEC_loginRequiredForm();
        $view = 'none';
    }
    break;

case 'savebillto':
case 'saveshipto':
    $addr_type = substr($action, 4);   // get 'billto' or 'shipto'
    if ($actionval == 1 || $actionval == 2) {
        $addr = json_decode($_POST['addr'][$actionval], true);
    } else {
        $addr = $_POST;
    }
    $status = \Shop\Customer::isValidAddress($addr);
    if ($status != '') {
        $content .= SHOP_errMsg($status, $LANG_SHOP['invalid_form']);
        $view = $addr_type;
        break;
    }
    $U = Shop\Customer::getInstance();
    if ($U->getUid() > 1) {      // only save addresses for logged-in users
        $addr_id = $U->saveAddress($addr, $addr_type);
        if ($addr_id[0] < 0) {
            if (!empty($addr_id[1]))
                $content .= SHOP_errorMessage($addr_id[1], 'alert',
                        $LANG_SHOP['missing_fields']);
            $view = $addr_type;
            break;
        } else {
            $_POST['useaddress'] = $addr_id[0];
        }
    }
    $Cart = Shop\Cart::getInstance();
    $Cart->setAddress($addr, $addr_type);
    $next_step = SHOP_getVar($_POST, 'next_step', 'integer');
    $content = $Cart->getView($next_step);
    $view = 'none';
    break;

case 'nextstep':
    $next_step = SHOP_getVar($_POST, 'next_step', 'integer');
    $content = Shop\Cart::getInstance()->getView($next_step);
    $view = 'none';
    break;

default:
    //$view = 'view';
    $view = $action;
    break;
}

switch ($view) {
case 'none':
    break;

case 'cancel':
    list($cart_id, $token) = explode('/', $actionval);
    if (!empty($cart_id)) {
        $Cart = \Shop\Cart::getInstance(0, $cart_id);
        if ($token == $Cart->getToken()) {
            \Shop\Cart::setFinal($cart_id, false);
            $Cart->setToken();
        }
    }
    // fall through to view cart
case 'view':
case 'editcart':
default:
    SHOP_setUrl($_SERVER['request_uri']);
    $menu_opt = $LANG_SHOP['viewcart'];
    $Cart = \Shop\Cart::getInstance();
    if ($view != 'editcart' && $Cart->canFastCheckout()) {
        $content .= $Cart->getView(1);
        break;
    }

    // Validate the cart items
    $invalid = $Cart->updateItems();
    $Cart->validateDiscountCode();
    if (!empty($invalid)) {
        // Items have been removed, refresh to update and view the info msg.
        COM_refresh(SHOP_URL . '/cart.php');
    }

    if ($Cart->hasItems() && $Cart->canView()) {
        $content .= $Cart->getView(0);
    } else {
        COM_setMsg($LANG_SHOP['cart_empty']);
        COM_refresh(SHOP_URL . '/index.php');
        exit;
    }
    break;
}

$display = \Shop\Menu::siteHeader();
$display .= \Shop\Menu::pageTitle('', 'cart');
$display .= $content;
$display .= \Shop\Menu::siteFooter();
echo $display;

?>
