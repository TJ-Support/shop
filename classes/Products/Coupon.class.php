<?php
/**
 * Class to handle coupon operations.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.6.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 *
 */
namespace Shop\Products;

/**
 * Class for coupons.
 * @package shop
 */
class Coupon extends \Shop\Product
{
    /** Maximum possible expiration date.
     * Used as a default for purchased coupons.
     * @const string */
    const MAX_EXP = '9999-12-31';

    /**
     * Constructor. Set up local variables.
     *
     * @param   integer $prod_id    Product ID
     */
    public function __construct($prod_id = 0)
    {
        global $LANG_SHOP;

        parent::__construct($prod_id);
        $this->prod_type == SHOP_PROD_COUPON;
        $this->taxable = 0; // coupons are not taxable

        // Add special fields for Coupon products
        // Relies on $LANG_SHOP for the text prompts
        $this->addSpecialField('recipient_email');
        $this->addSpecialField('sender_name');
        $this->addSpecialField('gc_message', $LANG_SHOP['message'], array('type'=>'textarea'));
    }


    /**
     * Generate a single coupon code based on options given.
     * Mask, if used, is "XXXX-XXXX" where "X" indicates a character and any
     * other character is passed through.
     * Based on https://github.com/joashp/simple-php-coupon-code-generator.
     *
     * @author      Joash Pereira
     * @author      Alex Rabinovich
     * @see         https://github.com/joashp/simple-php-coupon-code-generator
     * @return  string      Coupon code
     */
    public static function generate()
    {
        global $_SHOP_CONF;

        $length = SHOP_getVar($_SHOP_CONF, 'gc_length', 'int', 10);
        $prefix = $_SHOP_CONF['gc_prefix'];
        $suffix = $_SHOP_CONF[ 'gc_suffix'];
        $useLetters = SHOP_getVar($_SHOP_CONF, 'gc_letters', 'int');
        $useNumbers = SHOP_getVar($_SHOP_CONF, 'gc_numbers', 'int');
        $useSymbols = SHOP_getVar($_SHOP_CONF, 'gc_symbols', 'int');
        $mask = $_SHOP_CONF['gc_mask'];

        $uppercase  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase  = 'abcdefghijklmnopqrstuvwxyz';
        $numbers    = '1234567890';
        $symbols    = '`~!@#$%^&*()-_=+[]{}|";:/?.>,<';

        $characters = array();
        $coupon = '';

        switch ($useLetters) {
        case 1:     // uppercase only
            $characters = $uppercase;
            break;
        case 2:     // lowercase only
            $characters = $lowercase;
            break;
        case 3:     // both upper and lower
            $characters = $uppercase . $lowercase;
            break;
        case 0:     // no letters
        default:
            break;
        }
        if ($useNumbers) {
            $characters .= $numbers;
        }
        if ($useSymbols) {
            $characters .= $symbols;
        }
        $charcount = strlen($characters);

        // If a mask is specified, use it and substitute 'X' for coupon chars.
        // Otherwise use the specified length.
        if ($mask) {
            $len = strlen($mask);
            for ($i = 0; $i < $len; $i++) {
                if ($mask[$i] === 'X') {
                    $coupon .= $characters[mt_rand(0, $charcount - 1)];
                } else {
                    $coupon .= $mask[$i];
                }
            }
        } else {
            // if neither mask nor length given use a default length
            if ($length == 0) $length = 16;
            for ($i = 0; $i < $length; $i++) {
                $coupon .= $characters[mt_rand(0, $charcount - 1)];
            }
        }
        return $prefix . $coupon . $suffix;
    }


    /**
     * Generate a number of coupon codes.
     *
     * @param   integer $num        Number of coupon codes
     * @param   array   $options    Options for code creation
     * @return  array       Array of coupon codes
     */
    public static function generate_coupons($num = 1, $options = array())
    {
        $coupons = array();
        for ($i = 0; $i < $num; $i++) {
            $coupons[] = self::generate($options);
        }
        return $coupons;
    }


    /**
     * Record a coupon purchase.
     *
     * @param   float   $amount     Coupon value
     * @param   integer $uid        User ID, default = current user
     * @param   string  $exp        Expiration date
     * @return  mixed       Coupon code, or false on error
     */
    public static function Purchase($amount = 0, $uid = 0, $exp = self::MAX_EXP)
    {
        global $_TABLES, $_USER;

        if ($amount == 0) return false;
        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $options = array();     // Use all options from global config
        do {
            // Make sure there are no duplicates
            $code = self::generate($options);
            $code = DB_escapeString($code);
        } while (DB_count($_TABLES['shop.coupons'], 'code', $code));

        $uid = (int)$uid;
        $exp = DB_escapeString($exp);
        $amount = (float)$amount;
        $sql = "INSERT INTO {$_TABLES['shop.coupons']} SET
                code = '$code',
                buyer = $uid,
                amount = $amount,
                balance = $amount,
                purchased = UNIX_TIMESTAMP(),
                expires = '$exp'";
        DB_query($sql);
        return DB_error() ? false : $code;
    }


    /**
     * Apply a coupon to the user's account.
     * Adds the value to the gc_bal field in user info, and marks the coupon
     * as "redeemed" so it can't be used again.
     * Status code returned will be 0=success, 1=already done, 2=error
     *
     * @param   string  $code   Coupon code
     * @param   integer $uid    Optional user ID, current user by default
     * @return  array       Array of (Status code, Message)
     */
    public static function Redeem($code, $uid = 0)
    {
        global $_TABLES, $_USER, $LANG_SHOP, $_CONF;

        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $uid = (int)$uid;
        if ($uid < 2) {
            return array(2, sprintf($LANG_SHOP['coupon_apply_msg2'], $_CONF['site_email']));
        }

        $code = DB_escapeString($code);
        $sql = "SELECT * FROM {$_TABLES['shop.coupons']}
                WHERE code = '$code'";
        $res = DB_query($sql);
        if (DB_numRows($res) == 0) {
            SHOP_log("Attempting to redeem coupon $code, not found in database", SHOP_LOG_ERROR);
            return array(3, sprintf($LANG_SHOP['coupon_apply_msg3'], $_CONF['site_mail']));;
        } else {
            $A = DB_fetchArray($res, false);
            if ($A['redeemed'] > 0 && $A['redeemer'] > 0) {
                SHOP_log("Coupon code $code was already redeemed", SHOP_LOG_ERROR);
                return array(1, $LANG_SHOP['coupon_apply_msg1']);
            }
        }
        $amount = (float)$A['amount'];
        if ($amount > 0) {
            DB_query("UPDATE {$_TABLES['shop.coupons']} SET
                    redeemer = $uid,
                    redeemed = UNIX_TIMESTAMP()
                    WHERE code = '$code'");
            \Shop\Cache::clear('coupons');
            self::writeLog($code, $uid, $amount, 'gc_redeemed');
            if (DB_error()) {
                SHOP_error("A DB error occurred marking coupon $code as redeemed", SHOP_LOG_ERROR);
                return array(2, sprintf($LANG_SHOP['coupon_apply_msg2'], $_CONF['site_email']));
            }
        }
        return array(0, sprintf($LANG_SHOP['coupon_apply_msg0'], \Shop\Currency::getInstance()->Format($A['amount'])));
    }


    /**
     * Apply a coupon value against an order.
     * Does not update the coupon table, but deducts the maximum of the
     * coupon balance or the order value from the userinfo table.
     *
     * @param   float   $amount     Amount to redeem (order value)
     * @param   integer $uid        User ID redeeming the coupon
     * @param   object  $Order      Order object
     * @return  float               Remaining order value, if any
     */
    public static function Apply($amount, $uid = 0, $Order = NULL)
    {
        global $_TABLES, $_USER;

        if ($uid == 0) $uid = $_USER['uid'];
        $order_id = '';
        if (is_object($Order) && !$Order->isNew) {
            $order_id = DB_escapeString($Order->order_id);
            $uid = $Order->uid;
        }
        if ($uid < 2) return 0;
        $coupons = self::getUserCoupons($uid);
        $remain = (float)$amount;
        $applied = 0;
        foreach ($coupons as $coupon) {
            $bal = (float)$coupon['balance'];
            $code = DB_escapeString($coupon['code']);
            if ($bal > $remain) {
                // Coupon balance is enough to cover the remaining amount
                $bal -= $remain;
                $applied += $remain;
                $remain = 0;
            } else {
                // Apply the total balance on this coupon and loop to the next one
                $remain -= $bal;
                $applied += $bal;
                $bal = 0;
            }
            $sql = "UPDATE {$_TABLES['shop.coupons']}
                    SET balance = $bal
                    WHERE code = '$code';";
            self::writeLog($code, $uid, $applied, 'gc_applied', $order_id);
            DB_query($sql);
            if ($remain == 0) break;
        }
        \Shop\Cache::clear('coupons_' . $uid);
        return $remain;     // Return unapplied balance
    }


    /**
     * Handle the purchase of this item.
     *
     * @param  object  $Item       Item object, to get options, etc.
     * @param  object  $Order      Order object
     * @param  array   $ipn_data   Shop IPN data
     * @return integer     Zero or error value
     */
    public function handlePurchase(&$Item, $Order=NULL, $ipn_data=array())
    {
        global $LANG_SHOP;

        $status = 0;
        $amount = (float)$Item->price;
        $special = SHOP_getVar($Item->extras, 'special', 'array');
        $recip_email = SHOP_getVar($special, 'recipient_email', 'string');
        $sender_name = SHOP_getVar($special, 'sender_name', 'string');
        $msg = SHOP_getVar($special, 'message', 'string');
        $uid = $Item->getOrder()->uid;
        $gc_code = self::Purchase($amount, $uid);
        // Add the code to the options text. Saving the item will happen
        // next during addSpecial
        $Item->addOptionText($LANG_SHOP['code'] . ': ' . $gc_code, false);
        $Item->addSpecial('gc_code', $gc_code);

        parent::handlePurchase($Item, $Order);
        self::Notify($gc_code, $recip_email, $amount, $sender_name, $msg);
        return $status;
    }


    /**
     * Send a notification email to the recipient of the gift card.
     *
     * @param   string  $gc_code    Gift Cart Code
     * @param   string  $recip      Recipient Email, from the custom text field
     * @param   float   $amount     Gift Card Amount
     * @param   string  $sender     Optional sender, from the custom text field
     * @param   string  $exp        Expiration Date
     */
    public static function Notify($gc_code, $recip, $amount, $sender='', $msg='', $exp=self::MAX_EXP)
    {
        global $_CONF, $LANG_SHOP_EMAIL;

        if ($recip == '') {
            return;
        }

        SHOP_log("Sending Coupon to " . $recip, SHOP_LOG_DEBUG);
        $T = SHOP_getTemplate('coupon_email_message', 'message');
        if ($exp != self::MAX_EXP) {
            $dt = new \Date($exp, $_CONF['timezone']);
            $exp = $dt->format($_CONF['shortdate']);
        }
        $T->set_var(array(
            'gc_code'   => $gc_code,
            'sender_name' => $sender,
            'expires'   => $exp,
            'submit_url' => self::redemptionUrl($gc_code),
            'message'   => strip_tags($msg),
        ) );
        $T->parse('output', 'message');
        $msg_text = $T->finish($T->get_var('output'));
        COM_emailNotification(array(
                'to' => array(array('email'=>$recip, 'name' => $recip)),
                'from' => $_CONF['site_mail'],
                'htmlmessage' => $msg_text,
                'subject' => $LANG_SHOP_EMAIL['coupon_subject'],
        ) );
    }


    /**
     * Get additional text to add to the buyer's recipt for a product.
     * For coupons, add links to redeem against an account.
     *
     * @param   object  $item   Order Item object, to get the code
     * @return  string          Additional message to include in email
     */
    public function EmailExtra($item)
    {
        global $LANG_SHOP;

        $code = SHOP_getVar($item->extras['special'], 'gc_code', 'string');
        $s = '';
        if (!empty($code)) {
            $url = self::redemptionUrl($code);
            $s = sprintf(
                $LANG_SHOP['apply_gc_email'],
                $url,
                $url,
                $url
            );
        }
        return $s;
    }


    /**
     * Get the display price for the catalog.
     * Returns "See Details" if the price is zero, or the price if
     * one is set.
     *
     * @param   mixed   $price  Fixed price override (not used)
     * @return  string          Formatted price, or "See Details"
     */
    public function getDisplayPrice($price = NULL)
    {
        global $LANG_SHOP;

        $price = $this->getPrice();
        if ($price == 0) {
            return $LANG_SHOP['see_details'];
        } else {
            return \Shop\Currency::getInstance()->Format($price);
        }
    }


    /**
     * Get all the current Gift Card records for a user.
     * If $all is true then all records are returned, if false then only
     * those that are not redeemed and not expired are returned.
     *
     * @param   integer $uid    User ID, default = curent user
     * @param   boolean $all    True to get all, False to get currently usable
     * @return  array           Array of gift card records
     */
    public static function getUserCoupons($uid = 0, $all = false)
    {
        global $_TABLES, $_USER;

        if ($uid == 0) $uid = $_USER['uid'];
        $uid = (int)$uid;
        if ($uid < 2) return array();   // Can't get anonymous coupons here

        $all = $all ? 1 : 0;
        $cache_key = 'coupons_' . $uid . '_' . $all;
        $updatecache = false;       // indicator that cache must be updated
        $coupons = \Shop\Cache::get($cache_key);
        $today = date('Y-m-d');
        if ($coupons === NULL) {
            // cache not found, read all non-expired coupons
            $coupons = array();
            $sql = "SELECT * FROM {$_TABLES['shop.coupons']}
                WHERE redeemer = '$uid'";
            if (!$all) {
                $sql .= " AND expires >= '$today' AND balance > 0";
            }
            $sql .= " ORDER BY redeemed ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $coupons[] = $A;
            }
            $updatecache = true;
        } else {
            // Check the cached expiration dates in case any expired.
            foreach ($coupons as $idx=>$coupon) {
                if ($coupon['expires'] < $today) {
                    unset($coupons[$idx]);
                    $updatecache = true;
                }
            }
        }

        // If coupons were read from the DB, or any cached ones expired,
        // update the cache
        if ($updatecache) {
            \Shop\Cache::set(
                $cache_key,
                $coupons,
                array('coupons', 'coupons_' . $uid),
                3600
            );
        }
        return $coupons;
    }


    /**
     * Get the current unused Gift Card balance for a user.
     *
     * @param   integer $uid    User ID, default = current user
     * @return  float           User's gift card balance
     */
    public static function getUserBalance($uid = 0)
    {
        global $_USER;

        if ($uid == 0) $uid = $_USER['uid'];
        if ($uid == 1) return 0;    // no coupon bal for anonymous

        // Total up the available balances from the coupons table
        $bal = (float)0;
        $coupons = self::getUserCoupons($uid);
        foreach ($coupons as $coupon) {
            $bal += $coupon['balance'];
        }
        return (float)$bal;
    }


    /**
     * Verifies that the given user has a sufficient balance to cover an amount.
     *
     * @uses    self::getUserBalance()
     * @param   float   $amount     Amount to check
     * @param   integer $uid        User ID, default = current user
     * @return  boolean             True if the GC balance is sufficient.
     */
    public static function verifyBalance($amount, $uid = 0)
    {
        $amount = (float)$amount;
        $balance = self::getUserBalance($uid);
        return $amount <= $balance ? true : false;
    }


    /**
     * Write a log entry.
     *
     * @param   string  $code       Gift card code
     * @param   integer $uid        User ID
     * @param   float   $amount     Gift card amount or amount applied
     * @param   string  $msg        Message to log
     * @param   string  $order_id   Order ID (when applying)
     */
    public static function writeLog($code, $uid, $amount, $msg, $order_id = '')
    {
        global $_TABLES;

        $msg = DB_escapeString($msg);
        $order_id = DB_escapeString($order_id);
        $code = DB_escapeString($code);
        $amount = (float)$amount;
        $uid = (int)$uid;

        $sql = "INSERT INTO {$_TABLES['shop.coupon_log']}
                (code, uid, order_id, ts, amount, msg)
                VALUES
                ('{$code}', '{$uid}', '{$order_id}', UNIX_TIMESTAMP(), '$amount', '{$msg}');";
        DB_query($sql);
    }


    /**
     * Get the log entries for a user ID to show in their account.
     * Optionally specify a gift card code to get only entries
     * pertaining to that gift card.
     *
     * @param   integer $uid    User ID
     * @param   string  $code   Optional gift card code
     * @return  array           Array of log messages
     */
    public static function getLog($uid, $code = '')
    {
        global $_TABLES, $LANG_SHOP;

        $log = array();
        $uid = (int)$uid;
        $sql = "SELECT * FROM {$_TABLES['shop.coupon_log']}
                WHERE uid = $uid";
        if ($code != '') {
            $sql .= " AND code = '" . DB_escapeString($code) . "'";
        }
        $sql .= ' ORDER BY ts DESC';
        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $log[] = $A;
            }
        }
        return $log;
    }


    /**
     * From a cart, get the total items that can be paid by gift card.
     * Start with the order total and deduct any coupon items.
     *
     * @param   object  $cart   Shopping Cart
     * @return  float           Total payable by gift card
     */
    public static function canPayByGC($cart)
    {
        $gc_can_apply = $cart->getTotal();
        $items = $cart->getItems();
        foreach ($items as $item) {
            $P = $item->getProduct();
            if ($P->isNew || $P->prod_type == SHOP_PROD_COUPON) {
                $gc_can_apply -= $P->getPrice($item->options, $item->quantity) * $item->quantity;
            }
        }
        if ($gc_can_apply < 0) $gc_can_apply = 0;
        return $gc_can_apply;
    }


    /**
     * Determine if the current user has access to view this product.
     * Checks that gift cards are enabled in the configuration, then
     * checks the general product hasAccess() function.
     *
     * @return  boolean     True if access and purchase is allowed.
     */
    public function hasAccess()
    {
        global $_SHOP_CONF;

        if (!$_SHOP_CONF['gc_enabled']) {
            return false;
        } else {
            return parent::hasAccess();
        }
    }


    /**
     * Get the fixed quantity that can be ordered per item view.
     * If this is zero, then an input box will be shown for the buyer to enter
     * a quantity. If nonzero, then the input box is a hidden variable with
     * the value set to the fixed quantity
     *
     * @return  integer    Fixed quantity number, zero for varible qty
     */
    public function getFixedQuantity()
    {
        return 1;
    }


    /**
     * Determine if like items can be accumulated in the cart as a single item.
     *
     * @return  boolean     False, Gift cards are never accumulated.
     */
    public function cartCanAccumulate()
    {
        return false;
    }


    /**
     * Expire one or more coupons.
     * If $code is empty, then all coupons with a balance > 0 that have
     * expired are updated.
     *
     * @param   string  $code   Optional code to expire one coupon
     */
    public static function Expire($code='')
    {
        global $_TABLES, $_CONF;

        $sql = "SELECT * FROM {$_TABLES['shop.coupons']} ";
        if ($code == '') {
            $today = $_CONF['_now']->format('Y-m-d', true);
            $sql .= "WHERE balance > 0 AND expires < '$today'";
        } else {
            $code = DB_escapeString($code);
            $sql .= "WHERE balance > 0 AND code =  '$code'";
        }
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $c = DB_escapeString($A['code']);
            $sql1 = "UPDATE {$_TABLES['shop.coupons']}
                SET balance = 0
                WHERE code = '$c';";
            DB_query($sql1);
            self::writeLog($c, $A['redeemer'], $A['balance'], 'gc_expired');
        }
        if (count($A) > 0) {
            // If there were any updates, clear the coupon cache
            \Shop\Cache::clear('coupons');
        }
    }


    /**
     * Get the link to redeem a coupon code.
     * The link is to the redemption form if no code is provided.
     *
     * @param   string  $code   Coupon Code
     * @return  string      URL to redeem the code
     */
    public static function redemptionUrl($code = '')
    {
        $url = SHOP_URL . '/coupon.php?mode=redeem';
        if ($code !== '') {
            $url .= '&id=' . $code;
        }
        return COM_buildUrl($url);
    }


    /**
     * Purge all coupons and transactions from the database.
     * No safety check or confirmation is done; that should be done before
     * calling this function.
     */
    public static function Purge()
    {
        global $_TABLES;

        DB_query("TRUNCATE {$_TABLES['shop.coupons']}");
        DB_query("TRUNCATE {$_TABLES['shop.coupon_log']}");
    }


    /**
     * Get the text string and value for special fields.
     * Used when displaying cart info.
     * Overrides parent function to exclude the custom message field.
     *
     * @param   array   $values     Special field values
     * @return  array       Array of text=>value
     */
    public function getSpecialFields($values = array())
    {
        global $LANG_SHOP;

        $retval = array();
        if (empty($values)) {
            return $retval;
        }
        foreach ($this->special_fields as $fld_name=>$fld) {
            if ($fld_name == 'gc_message') {
                continue;
            }
            if (array_key_exists($fld_name, $values) && !empty($values[$fld_name])) {
                $retval[$fld['text']] = $values[$fld_name];
            }
        }
        return $retval;
    }

}

?>