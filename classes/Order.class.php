<?php
/**
 * Order class for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Order class.
 * @package shop
 */
class Order
{
    const PROCESSING = 'processing';
    const SHIPPED = 'shipped';
    const CLOSED = 'closed';

    /** Session variable name for storing cart info.
     * @var string */
    protected static $session_var = 'glShopCart';

    /** Flag to indicate that administrative actions are being done.
     * @var boolean */
    private $isAdmin = false;

    /** Internal properties set via `__set()` and `__get()`.
     * @var array */
    private $properties = array();

    /** Flag to indicate that this order has been finalized.
     * This is not related to the order status, but only to the current view
     * in the workflow.
     * @var boolean */
    private $isFinalView = false;

    /** Flag to indicate that this is a new record.
     * @var boolean */
    protected $isNew = true;

    /** Miscellaneious information values used by the Cart class.
     * @var array */
    protected $m_info = array();

    /** Flag to indicate that "no shipping" should be set.
     * @deprecated ?
     * @var boolean */
    private $no_shipping = 1;

    /** Address field names.
     * @var array */
    protected $_addr_fields = array(
        'name', 'company', 'address1', 'address2',
        'city', 'state', 'zip', 'country',
    );

    /** OrderItem objects.
     * @var array */
    protected $items = array();

    /** Order item total.
     * @var float */
    protected $subtotal = 0;

    /** Order final total, incl. shipping, handling, etc.
     * @var float */
    protected $total = 0;

    /** Number of taxable line items on the order.
     * @var integer */
    protected $tax_items = 0;

    /** Currency object, used for formatting amounts.
     * @var object */
    protected $Currency;

    /** Statuses that indicate an order is still in a "cart" phase.
     * @var array */
    protected static $nonfinal_statuses = array('cart', 'pending');

    /** Order Date.
     * This field is defined here since it contains an object and
     * needs to be accessed directly.
     * @var object */
    protected $order_date;

    /** Billing address object.
     * @var object */
    protected $Billto;

    /** Shipping address object.
     * @var object */
    protected $Shipto;


    /**
     * Set internal variables and read the existing order if an id is provided.
     *
     * @param   string  $id     Optional order ID to read
     */
    public function __construct($id='')
    {
        global $_USER, $_SHOP_CONF;

        $this->isNew = true;
        $this->uid = (int)$_USER['uid'];
        $this->instructions = '';
        $this->tax_rate = SHOP_getTaxRate();
        $this->currency = $_SHOP_CONF['currency'];
        if (!empty($id)) {
            $this->order_id = $id;
            if (!$this->Load($id)) {
                $this->isNew = true;
                $this->items = array();
            } else {
                $this->isNew = false;
            }
        }
        if ($this->isNew) {
            if (empty($id)) {
                // Only create a new ID if one wasn't supplied.
                // Carts may supply an ID that needs to be static.
                $this->order_id = self::_createID();
            }
            $this->order_date = SHOP_now();
            $this->token = $this->_createToken();
            $this->shipping = 0;
            $this->handling = 0;
            $this->by_gc = 0;
            $this->shipper_id = 0;
        }
    }


    /**
     * Get an object instance for an order.
     *
     * @param   string|array    $key    Order ID or record
     * @return  object          Order object
     */
    public static function getInstance($key)
    {
        static $orders = array();
        if (is_array($key)) {
            $id = SHOP_getVar($key, 'order_id');
        } else {
            $id = $key;
        }
        if (!empty($id)) {
            if (!array_key_exists($id, $orders)) {
                $orders[$id] = new self($id);
            }
            return $orders[$id];
        } else {
            return new self;
        }
    }


    /**
     * Set a property value.
     *
     * @param   string  $name   Name of property to set
     * @param   mixed   $value  Value to set
     */
    public function __set($name, $value)
    {
        switch ($name) {
        case 'uid':
        case 'billto_id':
        case 'shipto_id':
        case 'shipper_id':
            $this->properties[$name] = (int)$value;
            break;

        case 'tax':
        case 'tax_rate':
        case 'shipping':
        case 'handling':
        case 'by_gc':
        case 'ship_units':
            $this->properties[$name] = (float)$value;
            break;

        case 'order_seq':
        default:
            $this->properties[$name] = $value;
            break;
        }
    }


    /**
     * Return the value of a property, or NULL if the property is not set.
     *
     * @param   string  $name   Name of property to retrieve
     * @return  mixed           Value of property
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->properties)) {
            return $this->properties[$name];
        } else {
            return NULL;
        }
    }


    /**
     * Get the order status.
     *
     * @return  string      Order status
     */
    public function getStatus()
    {
        return $this->status;
    }



    /**
     * Load the order information from the database.
     *
     * @param   string  $id     Order ID
     * @return  boolean     True on success, False if order not found
     */
    public function Load($id = '')
    {
        global $_TABLES;

        if ($id != '') {
            $this->order_id = $id;
        }

        $cache_key = 'order_' . $this->order_id;
        //$A = Cache::get($cache_key);
        //if ($A === NULL) {
            $sql = "SELECT * FROM {$_TABLES['shop.orders']}
                    WHERE order_id='{$this->order_id}'";
            //echo $sql;die;
            $res = DB_query($sql);
            if (!$res) return false;    // requested order not found
            $A = DB_fetchArray($res, false);
            if (empty($A)) return false;
        //    Cache::set($cache_key, $A, 'orders');
            //}
        if ($this->setVars($A)) $this->isNew = false;

        // Now load the items
        //$cache_key = 'items_order_' . $this->order_id;
        //$items = Cache::get($cache_key);
        //if ($items === NULL) {
            $items = array();
            $sql = "SELECT * FROM {$_TABLES['shop.orderitems']}
                    WHERE order_id = '{$this->order_id}'";
            $res = DB_query($sql);
            if ($res) {
                while ($A = DB_fetchArray($res, false)) {
                    $items[$A['id']] = $A;
                }
            }
        //    Cache::set($cache_key, $items, array('items','orders'));
        //}
        // Now load the arrays into objects
        foreach ($items as $item) {
            $this->items[$item['id']] = new OrderItem($item);
        }
        return true;
    }


    /**
     * Add a single item to this order.
     * Extracts item information from the provided $data variable, and
     * reads the item information from the database as well.  The entire
     * item record is added to the $items array as 'data'
     *
     * @param   array   $args   Array of item data
     */
    public function addItem($args)
    {
        if (!is_array($args)) return;

        // Set the product_id if it is not supplied but the item_id is,
        // which is formated as "id|opt1,opt2,..."
        if (!isset($args['product_id'])) {
            $item_id = explode('|', $args['item_id']);  // TODO: DEPRECATE
            $args['product_id'] = $item_id[0];
        }
        $args['order_id'] = $this->order_id;    // make sure it's set
        $args['token'] = $this->_createToken();  // create a unique token
        $OI = new OrderItem($args);
        $OI->setQuantity($args['quantity']);
        $OI->Save();
        $this->items[] = $OI;
        $this->calcTotalCharges();
        //$this->Save();
    }


    /**
     * Set the billing address.
     *
     * @param   array   $A      Array of info, such as from $_POST
     */
    public function setBilling($A)
    {
        global $_TABLES;

        $addr_id = SHOP_getVar($A, 'useaddress', 'integer', 0);
        if ($addr_id == 0) {
            $addr_id = SHOP_getVar($A, 'addr_id', 'integer', 0);
        }
        if ($addr_id > 0) {
            // If set, the user has selected an existing address. Read
            // that value and use it's values.
            Cart::setSession('billing', $addr_id);
        }

        if (!empty($A)) {
            $this->billto_id        = $addr_id;
            $this->billto_name      = SHOP_getVar($A, 'name');
            $this->billto_company   = SHOP_getVar($A, 'company');
            $this->billto_address1  = SHOP_getVar($A, 'address1');
            $this->billto_address2  = SHOP_getVar($A, 'address2');
            $this->billto_city      = SHOP_getVar($A, 'city');
            $this->billto_state     = SHOP_getVar($A, 'state');
            $this->billto_country   = SHOP_getVar($A, 'country');
            $this->billto_zip       = SHOP_getVar($A, 'zip');
        }
        $sql = "UPDATE {$_TABLES['shop.orders']} SET
            billto_id   = '{$this->shipto_id}',
            billto_name = '" . DB_escapeString($this->billto_name) . "',
            billto_company = '" . DB_escapeString($this->billto_company) . "',
            billto_address1 = '" . DB_escapeString($this->billto_address1) . "',
            billto_address2 = '" . DB_escapeString($this->billto_address2) . "',
            billto_city = '" . DB_escapeString($this->billto_city) . "',
            billto_state = '" . DB_escapeString($this->billto_state) . "',
            billto_country = '" . DB_escapeString($this->billto_country) . "',
            billto_zip = '" . DB_escapeString($this->billto_zip) . "'
            WHERE order_id = '" . DB_escapeString($this->order_id) . "'";
        DB_query($sql);
        //Cache::delete('order_' . $this->order_id);
        return $this;
    }


    /**
     * Set the shipping address.
     *
     * @param   array|NULL  $A      Array of info, or NULL to clear
     * @return  object      Current Order object
     */
    public function setShipping($A)
    {
        global $_TABLES;

        if ($A === NULL) {
            // Clear out the shipping address
            $this->shipto_id        = 0;
            $this->shipto_name      = '';
            $this->shipto_company   = '';
            $this->shipto_address1  = '';
            $this->shipto_address2  = '';
            $this->shipto_city      = '';
            $this->shipto_state     = '';
            $this->shipto_country   = '';
            $this->shipto_zip       = '';
        } elseif (is_array($A)) {
            $addr_id = SHOP_getVar($A, 'useaddress', 'integer', 0);
            if ($addr_id == 0) {
                $addr_id = SHOP_getVar($A, 'addr_id', 'integer', 0);
            }
            if ($addr_id > 0) {
                // If set, read and use an existing address
                Cart::setSession('shipping', $addr_id);
            }
            if (!empty($A)) {
                $this->shipto_id        = $addr_id;
                $this->shipto_name      = SHOP_getVar($A, 'name');
                $this->shipto_company   = SHOP_getVar($A, 'company');
                $this->shipto_address1  = SHOP_getVar($A, 'address1');
                $this->shipto_address2  = SHOP_getVar($A, 'address2');
                $this->shipto_city      = SHOP_getVar($A, 'city');
                $this->shipto_state     = SHOP_getVar($A, 'state');
                $this->shipto_country   = SHOP_getVar($A, 'country');
                $this->shipto_zip       = SHOP_getVar($A, 'zip');
            }
        }
        $sql = "UPDATE {$_TABLES['shop.orders']} SET
            shipto_id   = '{$this->shipto_id}',
            shipto_name = '" . DB_escapeString($this->shipto_name) . "',
            shipto_company = '" . DB_escapeString($this->shipto_company) . "',
            shipto_address1 = '" . DB_escapeString($this->shipto_address1) . "',
            shipto_address2 = '" . DB_escapeString($this->shipto_address2) . "',
            shipto_city = '" . DB_escapeString($this->shipto_city) . "',
            shipto_state = '" . DB_escapeString($this->shipto_state) . "',
            shipto_country = '" . DB_escapeString($this->shipto_country) . "',
            shipto_zip = '" . DB_escapeString($this->shipto_zip) . "'
            WHERE order_id = '" . DB_escapeString($this->order_id) . "'";
        DB_query($sql);
        //Cache::delete('order_' . $this->order_id);
        return $this;
    }


    /**
     * Set all class variables, from a form or a database item
     *
     * @param   array   $A      Array of items
     * @return  object      Current Order object
     */
    public function setVars($A)
    {
        global $_USER, $_CONF, $_SHOP_CONF;

        if (!is_array($A)) return false;
        $tzid = COM_isAnonUser() ? $_CONF['timezone'] : $_USER['tzid'];

        $this->uid      = SHOP_getVar($A, 'uid', 'int');
        $this->status   = SHOP_getVar($A, 'status');
        $this->pmt_method = SHOP_getVar($A, 'pmt_method');
        $this->pmt_txn_id = SHOP_getVar($A, 'pmt_txn_id');
        $this->currency = SHOP_getVar($A, 'currency', 'string', $_SHOP_CONF['currency']);
        $dt = SHOP_getVar($A, 'order_date', 'integer');
        if ($dt > 0) {
            $this->order_date = new \Date($dt, $tzid);
        } else {
            $this->order_date = SHOP_now();
        }
        $this->order_id = SHOP_getVar($A, 'order_id');
        $this->shipping = SHOP_getVar($A, 'shipping', 'float');
        $this->handling = SHOP_getVar($A, 'handling', 'float');
        $this->tax = SHOP_getVar($A, 'tax', 'float');
        $this->instructions = SHOP_getVar($A, 'instructions');
        $this->by_gc = SHOP_getVar($A, 'by_gc', 'float');
        $this->token = SHOP_getVar($A, 'token', 'string');
        $this->buyer_email = SHOP_getVar($A, 'buyer_email');
        $this->billto_id = SHOP_getVar($A, 'billto_id', 'integer');
        $this->shipto_id = SHOP_getVar($A, 'shipto_id', 'integer');
        $this->order_seq = SHOP_getVar($A, 'order_seq', 'integer');
        if ($this->status != 'cart') {
            $this->tax_rate = SHOP_getVar($A, 'tax_rate');
        }
        $this->m_info = @unserialize(SHOP_getVar($A, 'info'));
        if ($this->m_info === false) $this->m_info = array();
        foreach (array('billto', 'shipto') as $type) {
            foreach ($this->_addr_fields as $name) {
                $fld = $type . '_' . $name;
                $this->$fld = $A[$fld];
            }
        }
        $this->Billto = new Address($this->getAddress('billto'));
        $this->Shipto = new Address($this->getAddress('shipto'));
        if (isset($A['uid'])) $this->uid = $A['uid'];

        if (isset($A['order_id']) && !empty($A['order_id'])) {
            $this->order_id = $A['order_id'];
            $this->isNew = false;
            Cart::setSession('order_id', $A['order_id']);
        } else {
            $this->order_id = '';
            $this->isNew = true;
            Cart::clearSession('order_id');
        }
        $this->shipper_id = $A['shipper_id'];
        return $this;
    }


    /**
     * API function to delete an entire order record.
     * Only orders that have a status of "cart" or "pending" can be deleted.
     * Finalized (paid, shipped, etc.) orders cannot  be removed.
     * Trying to delete a nonexistant order returns true.
     *
     * @param   string  $order_id       Order ID, taken from $_SESSION if empty
     * @return  boolean     True on success, False on error.
     */
    public static function Delete($order_id = '')
    {
        global $_TABLES;

        if ($order_id == '') {
            $order_id = Cart::getSession('order_id');
        }
        if (!$order_id) return true;

        $order_id = DB_escapeString($order_id);

        // Just get an instance of this order since there are a couple of values to check.
        $Ord = self::getInstance($order_id);
        if ($Ord->isNew) return true;

        // Only orders with no sequence number can be deleted.
        // Only orders with certain status values can be deleted.
        if ($Ord->order_seq !== NULL || $Ord->isFinal()) {
            return false;
        }

        // Checks passed, delete the order and items
        $sql = "START TRANSACTION;
            DELETE FROM {$_TABLES['shop.oi_opts']} WHERE oi_id IN (
                SELECT id FROM {$_TABLES['shop.orderitems']} WHERE order_id = '$order_id'
            );
            DELETE FROM {$_TABLES['shop.orderitems']} WHERE order_id = '$order_id';
            DELETE FROM {$_TABLES['shop.orders']} WHERE order_id = '$order_id';
            COMMIT;";
        DB_query($sql);
        //Cache::deleteOrder($order_id);
        return DB_error() ? false : true;
    }


    /**
     * Save the current order to the database
     *
     * @return  string      Order ID
     */
    public function Save()
    {
        global $_TABLES, $_SHOP_CONF;

        if (!SHOP_isMinVersion()) return '';

        // Save all the order items
        foreach ($this->items as $item) {
            $item->Save();
        }

        if ($this->isNew) {
            // Shouldn't have an empty order ID, but double-check
            if ($this->order_id == '') $this->order_id = self::_createID();
            if ($this->billto_name == '') {
                $this->billto_name = COM_getDisplayName($this->uid);
            }
            Cart::setSession('order_id', $this->order_id);
            // Set field values that can only be set once and not updated
            $sql1 = "INSERT INTO {$_TABLES['shop.orders']} SET
                    order_id='{$this->order_id}',
                    token = '" . DB_escapeString($this->token) . "',
                    uid = '" . (int)$this->uid . "', ";
            $sql2 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.orders']} SET ";
            $sql2 = " WHERE order_id = '{$this->order_id}'";
        }
        $this->calcTotalCharges();

        $fields = array(
                "order_date = '{$this->order_date->toUnix()}'",
                "status = '{$this->status}'",
                "pmt_txn_id = '" . DB_escapeString($this->pmt_txn_id) . "'",
                "pmt_method = '" . DB_escapeString($this->pmt_method) . "'",
                "by_gc = '{$this->by_gc}'",
                "phone = '" . DB_escapeString($this->phone) . "'",
                "tax = '{$this->tax}'",
                "shipping = '{$this->shipping}'",
                "handling = '{$this->handling}'",
                "instructions = '" . DB_escapeString($this->instructions) . "'",
                "buyer_email = '" . DB_escapeString($this->buyer_email) . "'",
                "info = '" . DB_escapeString(@serialize($this->m_info)) . "'",
                "tax_rate = '{$this->tax_rate}'",
                "currency = '{$this->currency}'",
                "shipper_id = '{$this->shipper_id}'",
        );
        foreach (array('billto', 'shipto') as $type) {
            $fld = $type . '_id';
            $fields[] = "$fld = " . (int)$this->$fld;
            foreach ($this->_addr_fields as $name) {
                $fld = $type . '_' . $name;
                $fields[] = $fld . "='" . DB_escapeString($this->$fld) . "'";
            }
        }
        $sql = $sql1 . implode(', ', $fields) . $sql2;
        //echo $sql;die;
        //SHOP_log(("Save: " . $sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        //Cache::deleteOrder($this->order_id);
        $this->isNew = false;
        return $this->order_id;
    }


    /**
     * View or print the current order.
     * Access is controlled by the caller invoking canView() since a token
     * may be required.
     *
     * @param  string  $view       View to display (cart, final order, etc.)
     * @param  integer $step       Current step, for updating next_step in the form
     * @return string      HTML for order view
     */
    public function View($view = 'order', $step = 0)
    {
        global $_SHOP_CONF, $_USER, $LANG_SHOP;

        $this->isFinalView = false;
        $is_invoice = true;    // normal view/printing view
        $icon_tooltips = array();

        switch ($view) {
        case 'order':
        case 'adminview';
            $this->isFinalView = true;
        case 'checkout':
            $tplname = 'order';
            break;
        case 'viewcart':
            $tplname = 'viewcart';
            break;
        case 'packinglist':
            // Print a packing list. Same as print view but no prices or fees shown.
            $tplname = 'packinglist';
            $is_invoice = false;
            $this->isFinalView = true;
            break;
        case 'print':
        case 'printorder':
            $this->isFinalView = true;
            $tplname = 'order.print';
            break;
        case 'pdfpl':
            $is_invoice = false;
        case 'pdforder':
            $this->isFinalView = true;
            $tplname = 'order.pdf';
            break;
        case 'shipment':
            $this->isFinalView = true;
            $tplname = 'shipment';
            break;
        }
        $step = (int)$step;

        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file('order', $tplname . '.thtml');
        foreach (array('billto', 'shipto') as $type) {
            foreach ($this->_addr_fields as $name) {
                $fldname = $type . '_' . $name;
                $T->set_var($fldname, $this->$fldname);
            }
        }

        // Set flags in the template to indicate which address blocks are
        // to be shown.
        foreach (Workflow::getAll($this) as $key => $wf) {
            $T->set_var('have_' . $wf->wf_name, 'true');
        }

        $T->set_block('order', 'ItemRow', 'iRow');

        $Currency = Currency::getInstance($this->currency);
        $this->no_shipping = 1;   // no shipping unless physical item ordered
        $this->subtotal = 0;
        $item_qty = array();        // array to track quantity by base item ID
        $have_images = false;
        $has_sale_items = false;
        foreach ($this->items as $item) {
            $P = $item->getProduct();
            if ($is_invoice) {
                $img = $P->getImage('', $_SHOP_CONF['order_tn_size']);
                if (!empty($img['url'])) {
                    $img_url = COM_createImage(
                        $img['url'],
                        '',
                        array(
                            'width' => $img['width'],
                            'height' => $img['height'],
                        )
                    );
                    $T->set_var('img_url', $img_url);
                    $have_images = true;
                } else {
                    $T->clear_var('img_url');
                }
            }

            //$item_discount = $P->getDiscount($item->quantity);
            /*if (!isset($item_qty[$item->product_id])) {
                $total_item_q = $this->getTotalBaseItems($item->product_id);
                $item_qty[$item->product_id] = array(
                    'qty'   => $total_item_q,
                    'discount' => $P->getDiscount($total_item_q),
                );
            }*/
            //if ($item_qty[$item->product_id]['discount'] > 0) {
            if ($item->getDiscount() > 0) {
                $discount_items ++;
                $price_tooltip = sprintf(
                    $LANG_SHOP['reflects_disc'],
                    ($item->getDiscount() * 100)
                );
            } else {
                $price_tooltip = '';
            }
            if ($item->getProduct()->isOnSale()) {
                $has_sale_items = true;
                $sale_tooltip = $LANG_SHOP['sale_price'] . ': ' . $item->getProduct()->getSale()->name;
            } else {
                $sale_tooltip = '';
            }

            $item_total = $item->getPrice() * $item->getQuantity();
            $this->subtotal += $item_total;
            if ($P->taxable) {
                $this->tax_items++;       // count the taxable items for display
            }
            $T->set_var(array(
                'cart_item_id'  => $item->getID(),
                'fixed_q'       => $P->getFixedQuantity(),
                'item_id'       => htmlspecialchars($item->getProductId()),
                'item_dscp'     => htmlspecialchars($item->getDscp()),
                'item_price'    => $Currency->FormatValue($item->getPrice()),
                'item_quantity' => $item->getQuantity(),
                'item_total'    => $Currency->FormatValue($item_total),
                'is_admin'      => $this->isAdmin,
                'is_file'       => $item->canDownload(),
                'taxable'       => $this->tax_rate > 0 ? $P->taxable : 0,
                'tax_icon'      => $LANG_SHOP['tax'][0],
                'sale_icon'     => $LANG_SHOP['sale_price'][0],
                'discount_icon' => 'D',
                'discount_tooltip' => $price_tooltip,
                'sale_tooltip'  => $sale_tooltip,
                'token'         => $item->getToken(),
                //'item_options'  => $P->getOptionDisplay($item),
                'item_options'  => $item->getOptionDisplay(),
                'sku'           => $P->getSKU($item),
                'item_link'     => $P->getLink($item->getID()),
                'pi_url'        => SHOP_URL,
                'is_invoice'    => $is_invoice,
                'del_item_url'  => COM_buildUrl(
                    SHOP_URL . '/cart.php?action=delete&id=' . $item->getID()
                ),
            ) );
            if ($P->isPhysical()) {
                $this->no_shipping = 0;
            }
            $T->parse('iRow', 'ItemRow', true);
            $T->clear_var('iOpts');
        }

        if ($discount_items > 0) {
            $icon_tooltips[] = $LANG_SHOP['discount'][0] . ' = Includes discount';
        }
        if ($this->tax_items > 0) {
            $icon_tooltips[] = $LANG_SHOP['taxable'][0] . ' = ' . $LANG_SHOP['taxable'];
        }
        if ($has_sale_items) {
            $icon_tooltips[] = $LANG_SHOP['sale_price'][0] . ' = ' . $LANG_SHOP['sale_price'];
        }
        $this->total = $this->getTotal();     // also calls calcTax()
        // Only show the icon descriptions when the invoice amounts are shown
        if ($is_invoice) {
            $icon_tooltips = implode('<br />', $icon_tooltips);
        } else {
            $icon_tooltips = NULL;
        }
        $by_gc = (float)$this->getInfo('apply_gc');
        $ShopAddr = new Address($_SHOP_CONF);

        // Reload the address objects in case the addresses were updated
        $this->Billto = new Address($this->getAddress('billto'));
        $this->Shipto = new Address($this->getAddress('shipto'));

        // Call selectShipper() here to get the shipping amount into the local var.
        $shipper_select = $this->selectShipper();
        $T->set_var(array(
            'pi_url'        => SHOP_URL,
            'account_url'   => COM_buildUrl(SHOP_URL . '/account.php'),
            'pi_admin_url'  => SHOP_ADMIN_URL,
            'total'         => $Currency->Format($this->total),
            'not_final'     => !$this->isFinalView,
            'order_date'    => $this->order_date->format($_SHOP_CONF['datetime_fmt'], true),
            'order_date_tip' => $this->order_date->format($_SHOP_CONF['datetime_fmt'], false),
            'order_number'  => $this->order_id,
            'handling'      => $this->handling > 0 ? $Currency->FormatValue($this->handling) : 0,
            'subtotal'      => $this->subtotal == $this->total ? '' : $Currency->Format($this->subtotal),
            'order_instr'   => htmlspecialchars($this->instructions),
            'shop_name'     => $ShopAddr->toHTML('company'),
            'shop_addr'     => $ShopAddr->toHTML('address'),
            'shop_phone'    => $_SHOP_CONF['shop_phone'],
            'apply_gc'      => $by_gc > 0 ? $Currency->FormatValue($by_gc) : 0,
            'net_total'     => $Currency->Format($this->total - $by_gc),
            'cart_tax'      => $this->tax > 0 ? $Currency->FormatValue($this->tax) : 0,
            'lang_tax_on_items'  => sprintf($LANG_SHOP['tax_on_x_items'], $this->tax_rate * 100, $this->tax_items),
            'status'        => $this->status,
            'token'         => $this->token,
            'allow_gc'      => $_SHOP_CONF['gc_enabled']  && !COM_isAnonUser() ? true : false,
            'next_step'     => $step + 1,
            'not_anon'      => !COM_isAnonUser(),
            'total_prefix'  => $Currency->Pre(),
            'total_postfix' => $Currency->Post(),
            'total_num'     => $Currency->FormatValue($this->total),
            'cur_decimals'  => $Currency->Decimals(),
            'item_subtotal' => $Currency->FormatValue($this->subtotal),
            'return_url'    => SHOP_getUrl(),
            'is_invoice'    => $is_invoice,
            'icon_dscp'     => $icon_tooltips,
            'print_url'     => $this->buildUrl('print'),
            'have_images'   => $is_invoice ? $have_images : false,
            'linkPackingList' => self::linkPackingList($this->order_id),
            'linkPrint'     => self::linkPrint($this->order_id, $this->token),
            'billto_addr'   => $this->Billto->toHTML(),
            'shipto_addr'   => $this->Shipto->toHTML(),
            'shipment_block' => $this->getShipmentBlock(),
            'itemsToShip'   => $this->itemsToShip(),
            'ret_url'       => urlencode($_SERVER['REQUEST_URI']),
        ) );

        if (!$this->no_shipping) {
            $T->set_var(array(
                'shipper_id'    => $this->shipper_id,
                'ship_method'   => Shipper::getInstance($this->shipper_id)->name,
                'ship_select'   => $this->isFinalView ? NULL : $shipper_select,
                'shipping'      => $Currency->FormatValue($this->shipping),
            ) );
        }

        if ($this->isAdmin) {
            $T->set_var(array(
                'is_admin'      => true,
                'purch_name'    => COM_getDisplayName($this->uid),
                'purch_uid'     => $this->uid,
                //'stat_update'   => OrderStatus::Selection($this->order_id, 1, $this->status),
                'order_status'  => $this->status,
            ) );
            $T->set_block('ordstat', 'StatusSelect', 'Sel');
            foreach (OrderStatus::getAll() as $key => $data) {
                if (!$data->enabled) continue;
                $T->set_var(array(
                    'selected' => $key == $this->status ? 'selected="selected"' : '',
                    'stat_key' => $key,
                    'stat_descr' => OrderStatus::getDscp($key),
                ) );
                $T->parse('Sel', 'StatusSelect', true);
            }
        }

        // Instantiate a date objet to handle formatting of log timestamps
        $dt = new \Date('now', $_USER['tzid']);
        $log = $this->getLog();
        $T->set_block('order', 'LogMessages', 'Log');
        foreach ($log as $L) {
            $dt->setTimestamp($L['ts']);
            $T->set_var(array(
                'log_username'  => $L['username'],
                'log_msg'       => $L['message'],
                'log_ts'        => $dt->format($_SHOP_CONF['datetime_fmt'], true),
                'log_ts_tip'    => $dt->format($_SHOP_CONF['datetime_fmt'], false),
            ) );
            $T->parse('Log', 'LogMessages', true);
        }

        $payer_email = $this->buyer_email;
        if ($payer_email == '' && !COM_isAnonUser()) {
            $payer_email = $_USER['email'];
        }
        $focus_fld = SESS_getVar('shop_focus_field');
        if ($focus_fld) {
            $T->set_var('focus_element', $focus_fld);
            SESS_unSet('shop_focus_field');
        }
        $T->set_var('payer_email', $payer_email);

        switch ($view) {
        case 'viewcart':
            $T->set_var('gateway_radios', $this->getCheckoutRadios());
            break;
        case 'checkout':
            $gw = Gateway::getInstance($this->getInfo('gateway'));
            if ($gw) {
                $T->set_var(array(
                    'gateway_vars'  => $this->checkoutButton($gw),
                    'checkout'      => 'true',
                    'pmt_method'    => $gw->Description(),
                ) );
            }
        default:
            break;
        }

        $status = $this->status;
        if ($this->pmt_method != '') {
            $gw = Gateway::getInstance($this->pmt_method);
            if ($gw !== NULL) {
                $pmt_method = $gw->Description();
            } else {
                $pmt_method = $this->pmt_method;
            }

            $T->set_var(array(
                'pmt_method' => $pmt_method,
                'pmt_txn_id' => $this->pmt_txn_id,
                'ipn_det_url' => IPN::getDetailUrl($this->pmt_txn_id, 'txn_id'),
            ) );
        }

        $T->parse('output', 'order');
        $form = $T->finish($T->get_var('output'));
        return $form;
    }


    /**
     * Update the order's status flag to a new value.
     * If the new status isn't really new, the order is unchanged and "true"
     * is returned.  If this is called by some automated process, $log can
     * be set to "false" to avoid logging the change, such as during order
     * creation.
     *
     * @uses    Order::Log()
     * @param   string  $newstatus      New order status
     * @param   boolean $log            True to log the change, False to not
     * @param   boolean $notify         True to notify the buyer, False to not.
     * @return  string      New status, old status if not updated.
     */
    public function updateStatus($newstatus, $log = true, $notify=true)
    {
        global $_TABLES, $LANG_SHOP;

        $oldstatus = $this->status;
        // If the status isn't really changed, don't bother updating anything
        // and just treat it as successful
        if ($oldstatus == $newstatus) {
            return $oldstatus;
        }

        $this->status = $newstatus;
        $db_order_id = DB_escapeString($this->order_id);
        $log_user = $this->log_user;

        // Clear the order from cache
        //Cache::delete('order_' . $this->order_id);

        // If promoting from a cart status to a real order, add the sequence number.
        if (!$this->isFinal($oldstatus) && $this->isFinal() && $this->order_seq < 1) {
            $sql = "START TRANSACTION;
                SELECT COALESCE(MAX(order_seq)+1,1) FROM {$_TABLES['shop.orders']} INTO @seqno FOR UPDATE;
                UPDATE {$_TABLES['shop.orders']}
                    SET status = '". DB_escapeString($newstatus) . "',
                    order_seq = @seqno
                WHERE order_id = '$db_order_id';
                COMMIT;";
            DB_query($sql);
            $this->order_seq = (int)DB_getItem($_TABLES['shop.orders'], 'order_seq', "order_id = '{$db_order_id}'");
        } else {
            // Update the status but leave the sequence alone
            $sql = "UPDATE {$_TABLES['shop.orders']} SET
                    status = '". DB_escapeString($newstatus) . "'
                WHERE order_id = '$db_order_id';";
            DB_query($sql);
        }
        //echo $sql;die;
        //SHOP_log($sql, SHOP_LOG_DEBUG);
        if (DB_error()) {
            return $oldstatus;
        }
        //Cache::deleteOrder($this->order_id);
        $this->status = $newstatus;     // update in-memory object
        $msg = sprintf($LANG_SHOP['status_changed'], $oldstatus, $newstatus);
        if ($log) {
            $this->Log($msg, $log_user);
        }
        if ($notify) {
            $this->Notify($newstatus, $msg);
        }
        return $newstatus;
    }


    /**
     * Log a message related to this order.
     * Typically used to log status changes.  If this is called for an
     * order object, the local "log_user" variable can be preset to the
     * log user name.  Otherwise, the current user's display name will be
     * associated with the log entry.
     *
     * @param   string  $msg        Log message
     * @param   string  $log_user   Optional log username
     */
    public function Log($msg, $log_user = '')
    {
        global $_TABLES, $_USER;

        // Don't log empty messages by mistake
        if (empty($msg)) return;

        // If the order ID is omitted, get information from the current
        // object.
        if (empty($log_user)) {
            $log_user = COM_getDisplayName($_USER['uid']) .
                ' (' . $_USER['uid'] . ')';
        }
        $order_id = DB_escapeString($this->order_id);
        $sql = "INSERT INTO {$_TABLES['shop.order_log']} SET
            username = '" . DB_escapeString($log_user) . "',
            order_id = '$order_id',
            message = '" . DB_escapeString($msg) . "',
            ts = UNIX_TIMESTAMP()";
        DB_query($sql);
        if (!DB_error()) {
            $cache_key = 'orderlog_' . $order_id;
            Cache::delete($cache_key);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Get the last log entry.
     * Called from admin ajax to display the log after the status is updated.
     * Resets the "ts" field to the formatted timestamp.
     *
     * @return  array   Array of DB fields.
     */
    public function getLastLog()
    {
        global $_TABLES, $_SHOP_CONF, $_USER;

        $sql = "SELECT * FROM {$_TABLES['shop.order_log']}
                WHERE order_id = '" . DB_escapeString($this->order_id) . "'
                ORDER BY ts DESC
                LIMIT 1";
        //echo $sql;die;
        if (!DB_error()) {
            $L = DB_fetchArray(DB_query($sql), false);
            if (!empty($L)) {
                $dt = new \Date($L['ts'], $_USER['tzid']);
                $L['ts'] = $dt->format($_SHOP_CONF['datetime_fmt'], true);
            }
        }
        return $L;
    }


    /**
     * Send an email to the administrator and/or buyer.
     *
     * @param   string  $status     Order status (pending, paid, etc.)
     * @param   string  $gw_msg     Optional gateway message to include with email
     */
    public function Notify($status='', $gw_msg='')
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP;

        // Check if any notification is to be sent for this status update.
        $notify_buyer = OrderStatus::getInstance($status)->notifyBuyer();
        $notify_admin = OrderStatus::getInstance($status)->notifyAdmin();
        if (!$notify_buyer && !$notify_admin) {
            return;
        }

        $store_name = SHOP_getVar($_SHOP_CONF, 'company', 'string', $_CONF['site_name']);
        if (empty($store_name)) {
            $store_name = $_CONF['site_name'];  // company could be set but empty
        }
        $Cust = Customer::getInstance($this->uid);
        if ($notify_buyer) {
            $save_language = $LANG_SHOP;    // save the site language
            $save_userlang = $_CONF['language'];
            $_CONF['language'] = $Cust->getLanguage(true);
            $LANG_SHOP = self::loadLanguage($_CONF['language']);
            // Set up templates, using language-specific ones if available.
            // Fall back to English if no others available.
            $T = new \Template(array(
                SHOP_PI_PATH . '/templates/notify/' . $Cust->getLanguage(),
                SHOP_PI_PATH . '/templates/notify/' . COM_getLanguageName(),
                SHOP_PI_PATH . '/templates/notify/english',
                SHOP_PI_PATH . '/templates/notify', // catch templates using language strings
            ) );
            $T->set_file(array(
                'msg'       => 'msg_buyer.thtml',
                'msg_body'  => 'order_detail.thtml',
                'tracking'  => 'tracking_info.thtml',
            ) );

            $text = $this->_prepareNotification($T, $gw_msg, true);

            SHOP_log("Sending email to " . $this->uid . ' at ' . $this->buyer_email, SHOP_LOG_DEBUG);
            $subject = SHOP_getVar(
                $LANG_SHOP['subj_email_user'],
                $status,
                'string',
                $LANG_SHOP['subj_email']
            );
            $subject = sprintf($subject, $store_name);
            if ($this->buyer_email != '') {
                COM_emailNotification(array(
                    'to' => array($this->buyer_email),
                    'from' => array(
                        'email' => $_CONF['site_mail'],
                        'name'  => $store_name,
                    ),
                    'htmlmessage' => $text,
                    'subject' => htmlspecialchars($subject),
                ) );
                SHOP_log("Buyer Notification Done.", SHOP_LOG_DEBUG);
            }
            $LANG_SHOP = $save_language;    // Restore the default language
        }

        if ($notify_admin) {
            // Set up templates, using language-specific ones if available.
            // Fall back to English if no others available.
            // This uses the site default language.
            $Cust = Customer::getInstance($this->uid);
            $T = new \Template(array(
                SHOP_PI_PATH . '/templates/notify/' . COM_getLanguageName(),
                SHOP_PI_PATH . '/templates/notify/english',
                SHOP_PI_PATH . '/templates/notify', // catch templates using language strings
            ) );
            $T->set_file(array(
                'msg'       => 'msg_admin.thtml',
                'msg_body'  => 'order_detail.thtml',
            ) );

            $text = $this->_prepareNotification($T, $gw_msg, false);

            if (!empty($_SHOP_CONF['admin_email_addr'])) {
                $email_addr = $_SHOP_CONF['admin_email_addr'];
            } else {
                $email_addr = $_CONF['site_mail'];
            }
            SHOP_log("Sending email to admin at $email_addr", SHOP_LOG_DEBUG);
            if (!empty($email_addr)) {
                COM_emailNotification(array(
                    'to' => array(
                        'email' => $email_addr,
                        'name'  => $store_name,
                    ),
                    'from' => SHOP_getVar($_CONF, 'noreply_mail', 'string', $_CONF['site_mail']),
                    'htmlmessage' => $text,
                    'subject' => htmlspecialchars($LANG_SHOP['subj_email_admin']),
                ) );
                SHOP_log("Admin Notification Done.", SHOP_LOG_DEBUG);
            }
        }
    }


    /**
     * This function actually creates the text for notification emails.
     *
     * @param   object  &$T         Template object reference
     * @param   string  $gw_msg     Optional gateway message to include
     * @param   boolean $incl_trk   True to include package tracking info
     * @return  string      Text for email body
     */
    private function _prepareNotification(&$T, $gw_msg='', $incl_trk=true)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP;

        // Add all the items to the message
        $total = (float)0;      // Track total purchase value
        $files = array();       // Array of filenames, for attachments
        $item_total = 0;
        $dl_links = '';         // Start with empty download links
        $email_extras = array();
        $Cur = Currency::getInstance($this->curency);   // get currency object for formatting

        foreach ($this->items as $id=>$item) {
            $P = $item->getProduct();

            // Add the file to the filename array, if any. Download
            // links are only included if the order status is 'paid'
            $file = $P->file;
            if (!empty($file) && $this->status == 'paid') {
                $files[] = $file;
                $dl_url = SHOP_URL . '/download.php?';
                // There should always be a token, but fall back to the
                // product ID if there isn't
                if ($item->getToken() != '') {
                    $dl_url .= 'token=' . urlencode($item->getToken());
                    $dl_url .= '&i=' . $item->getID();
                } else {
                    $dl_url .= 'id=' . $item->getProductId();
                }
                $dl_links .= "<a href=\"$dl_url\">$dl_url</a><br />";
            }

            $ext = $item->getQuantity() * $item->getPrice();
            $item_total += $ext;
            $item_descr = $item->getShortDscp();
            $options_text = $item->getOptionDisplay();

            $T->set_block('msg_body', 'ItemList', 'List');
            $T->set_var(array(
                'qty'   => $item->getQuantity(),
                'price' => $Cur->FormatValue($item->getPrice()),
                'ext'   => $Cur->FormatValue($ext),
                'name'  => $item_descr,
                'options_text' => $options_text,
            ) );
            //), '', false, false);
            $T->parse('List', 'ItemList', true);
            $x = $P->EmailExtra($item);
            if ($x != '') $email_extras[] = $x;
        }

        $total_amount = $item_total + $this->tax + $this->shipping + $this->handling;
        $user_name = COM_getDisplayName($this->uid);
        if ($this->billto_name == '') {
            $this->billto_name = $user_name;
        }

        if ($incl_trk) {        // include tracking information block
            $Shipments = Shipment::getByOrder($this->order_id);
            if (count($Shipments) > 0) {
                foreach ($Shipments as $Shp) {
                    $shp_dt = $Shp->getDate()->toMySQL(true);
                    $Packages = $Shp->getPackages();
                    $T->set_block('tracking', 'trackingPackages', 'TP');
                    foreach ($Packages as $Pkg) {
                        $T->set_var(array(
                            'shipment_date' => $shp_dt,
                            'shipper_name'  => $Pkg->shipper_info,
                            'tracking_num'  => $Pkg->tracking_num,
                            'tracking_url'  => $Pkg->getTrackingURL(false),
                        ) );
                        $shp_dt = '';
                        $T->parse('TP', 'trackingPackages', true);
                    }
                }
                $T->set_var('tracking_info', $T->parse('detail', 'tracking'));
            }
        }

        $T->set_var(array(
            'payment_gross'     => $Cur->Format($total_amount),
            'payment_items'     => $Cur->Format($item_total),
            'tax'               => $Cur->FormatValue($this->tax),
            'tax_num'           => $this->tax,
            'shipping'          => $Cur->FormatValue($this->shipping),
            'shipper_id'        => $this->shipper_id,
            'handling'          => $Cur->FormatValue($this->handling),
            'handling_num'      => $this->handling,
            'payment_date'      => SHOP_now()->toMySQL(true),
            'payer_email'       => $this->buyer_email,
            'payer_name'        => $this->billto_name,
            'site_name'         => SHOP_getVar($_SHOP_CONF, 'company', 'string', $_CONF['site_name']),
            'txn_id'            => $this->pmt_txn_id,
            'pi_url'            => SHOP_URL,
            'pi_admin_url'      => SHOP_ADMIN_URL,
            'dl_links'          => $dl_links,
            'buyer_uid'         => $this->uid,
            'user_name'         => $user_name,
            'gateway_name'      => $this->pmt_method,
            'pmt_method'        => $this->pmt_method,
            'pending'           => $this->status == 'pending' ? 'true' : '',
            'gw_msg'            => $gw_msg,
            'status'            => $this->status,
            'order_instr'       => $this->instructions,
            'order_id'          => $this->order_id,
            'token'             => $this->token,
            'email_extras'      => implode('<br />' . LB, $email_extras),
            'order_date'        => $this->order_date->format($_SHOP_CONF['datetime_fmt'], true),
            'order_url'         => $this->buildUrl('view'),
        ) );
        //), '', false, false);

        $this->_setAddressTemplate($T);

        // If any part of the order is paid by gift card, indicate that and
        // calculate the net amount paid by shop, etc.
        if ($this->by_gc > 0) {
            $T->set_var(array(
                'by_gc'     => $Cur->FormatValue($this->by_gc),
                'net_total' => $Cur->Format($total_amount - $this->by_gc),
            ) );
            //), '', false, false);
        }

        // Show the remaining gift card balance, if any.
        $gc_bal = \Shop\Products\Coupon::getUserBalance($this->uid);
        if ($gc_bal > 0) {
            $T->set_var(array(
                'gc_bal_fmt' => $Cur->Format($gc_bal),
                'gc_bal_num' => $gc_bal,
            ) );
            //), '', false, false);
        }

        // parse templates for subject/text
        $T->set_var(
            'purchase_details',
            $T->parse('detail', 'msg_body') //,
//            '', false, false
        );
        $text = $T->parse('text', 'msg');
        return $text;
    }


    /**
     * Get the miscellaneous charges on this order.
     * Just a shortcut to adding up the non-item charges.
     *
     * @return  float   Total "other" charges, e.g. tax, shipping, etc.
     */
    public function miscCharges()
    {
        return $this->shipping + $this->handling + $this->tax;
    }


    /**
     * Check the user's permission to view this order or cart.
     *
     * @param   string  $token  Token provided by the user, if any
     * @return  boolean     True if allowed to view, False if denied.
     */
    public function canView($token='')
    {
        global $_USER;

        if ($this->isNew) {
            // Record not found in DB, or this is a cart (not an order)
            return false;
        } elseif (
            ($this->uid > 1 && $_USER['uid'] == $this->uid) ||
            plugin_ismoderator_shop()
        ) {
            // Administrator, or logged-in buyer
            return true;
        } elseif ($token !== '' && $token == $this->token) {
            // Correct token provided via parameter
            return true;
        } elseif (isset($_GET['token']) && $_GET['token'] == $this->token) {
            // Anonymous with the correct token
            return true;
        } else {
            // Unauthorized
            return false;
        }
    }


    /**
     * Get all the log entries for this order.
     *
     * @return  array   Array of log entries
     */
    public function getLog()
    {
        global $_TABLES, $_CONF;

        $order_id = DB_escapeString($this->order_id);
        $cache_key = 'orderlog_' . $order_id;
        $log = Cache::get($cache_key);
        if ($log === NULL) {
            $log = array();
            $sql = "SELECT * FROM {$_TABLES['shop.order_log']}
                    WHERE order_id = '$order_id'";
            $res = DB_query($sql);
            while ($L = DB_fetchArray($res, false)) {
                $log[] = $L;
            }
            Cache::set($cache_key, $log, 'order_log');
        }
        return $log;
    }


    /**
     * Calculate the tax on this order.
     * Sets the tax and tax_items properties and returns the tax amount.
     *
     * @return  float   Sales Tax amount
     */
    public function calcTax()
    {
        if ($this->tax_rate == 0) {
            $this->tax_items = 0;
            $this->tax = 0;
        } else {
            $tax = 0;
            $this->tax_items = 0;
            foreach ($this->items as $item) {
                if ($item->getProduct()->isTaxable()) {
                    $tax += Currency::getInstance($this->currency)
                        ->RoundVal($this->tax_rate * $item->getQuantity() * $item->getPrice());
                    $this->tax_items++;
                }
            }
            //$this->tax = Currency::getInstance()->RoundVal($this->tax_rate * $tax_amt);
            $this->tax = $tax;
        }
        return $this->tax;
    }


    /**
     * Calculate the total shipping fee for this order.
     * Sets $this->shipping, no return value.
     */
    public function calcShipping()
    {
        // Only calculate shipping if there are physical items,
        // otherwise shipping = 0
        if ($this->hasPhysical()) {
            $shipper_id = $this->shipper_id;
            $shippers = Shipper::getShippersForOrder($this);
            $have_shipper = false;
            if ($shipper_id !== NULL) {
                // Array is 0-indexed so search for the shipper ID, if any.
                foreach ($shippers as $id=>$shipper) {
                    if ($shipper->id == $shipper_id) {
                        // Use the already-selected shipper, if any.
                        // The ship_method var should already be set.
                        $this->shipping = $shippers[$id]->ordershipping->total_rate;
                        $have_shipper = true;
                        break;
                    }
                }
            }
            if (!$have_shipper) {
                // If the specified shipper isn't found for some reason,
                // get the first shipper available, which will be the best rate.
                $shipper = reset($shippers);
                $this->ship_method = $shipper->name;
                $this->shipping = $shipper->ordershipping->total_rate;
            }
        } else {
            $this->shipping = 0;
        }
    }


    /**
     * Calculate total additional charges: tax, shipping and handling..
     * Simply totals the amounts for each item.
     *
     * @return  float   Total additional charges
     */
    public function calcTotalCharges()
    {
        global $_SHOP_CONF;

        $this->handling = 0;
        foreach ($this->items as $item) {
            $P = $item->getProduct();
            $this->handling += $P->getHandling($item->getQuantity());
        }

        $this->calcTax();   // Tax calculation is slightly more complex
        $this->calcShipping();
        return $this->tax + $this->shipping + $this->handling;
    }


    /**
     * Create a random token string for this order.
     * Allows anonymous users to view the order from an email link.
     *
     * @return  string      Token string
     */
    private function _createToken()
    {
        $len = 12;      // Actual length of the token needed.
        if (function_exists("random_bytes")) {
            $bytes = random_bytes(ceil($len / 2));
        } elseif (function_exists("openssl_random_pseudo_bytes")) {
            $bytes = openssl_random_pseudo_bytes(ceil($len / 2));
        } else {
            $options = array(
                'length'    => ceil($len / 2),
                'letters'   => 3,       // mixed case
                'numbers'   => true,    // include numbers
                'symbols'   => true,    // include symbols
                'mask'      => '',
            );
            $bytes = \Shop\Products\Coupon::generate($options);
        }
        return substr(bin2hex($bytes), 0, $len);
    }


    /**
     * Set a new token on the order.
     * Used after an action is performed to prevent the same action from
     * happening again accidentally.
     *
     * @return  object  $this
     */
    public function setToken()
    {
        global $_TABLES;

        $token = $this->_createToken();
        $sql = "UPDATE {$_TABLES['shop.orders']}
            SET token = '" . DB_escapeString($token) . "'
            WHERE order_id = '" . DB_escapeString($this->order_id) . "'";
        DB_query($sql, 1);
        if (!DB_error()) {
            $this->token = $token;
            //Cache::clear('orders');
        }
        return $this;
    }


    /**
     * Get the order total, including tax, shipping and handling.
     *
     * @return  float   Total order amount
     */
    public function getTotal()
    {
        $total = 0;
        foreach ($this->items as $id => $item) {
            $total += ($item->getPrice() * $item->getQuantity());
        }
        if ($this->status == 'cart') {
            $total += $this->calcTotalCharges();
        } else {
            // Already have the amounts calculated, don't do it again
            // every time the order is viewed since rates may change.
            $total += $this->shipping + $this->tax + $this->handling;
        }
        return Currency::getInstance()->RoundVal($total);
    }


    /**
     * Set the isAdmin field to indicate whether admin access is being requested.
     *
     * @param   boolean $isAdmin    True to get admin view, False for user view
     * @return  object      Current Order object
     */
    public function setAdmin($isAdmin = false)
    {
        $this->isAdmin = $isAdmin == false ? false : true;
        return $this;
    }


    /**
     * Create the order ID.
     * Since it's transmitted in cleartext, it'd be a good idea to
     * use something more "encrypted" than just the session ID.
     * On the other hand, it can't be too random since it needs to be
     * repeatable.
     *
     * @return  string  Order ID
     */
    protected static function _createID()
    {
        global $_TABLES;
        if (function_exists('CUSTOM_shop_orderID')) {
            $func = 'CUSTOM_shop_orderID';
        } else {
            $func = 'COM_makeSid';
        }
        do {
            $id = COM_sanitizeID($func());
        } while (DB_getItem($_TABLES['shop.orders'], 'order_id', "order_id = '$id'") !== NULL);
        return $id;
    }


    /**
     * Check if an item already exists in the cart.
     * This can be used to determine whether to add the item or not.
     * Check for "false" return value as the return may be zero for the
     * first item in the cart.
     *
     * @param   string  $item_id    Item ID to check, e.g. "1|2,3,4"
     * @param   array   $extras     Option custom values, e.g. text fields
     * @return  integer|boolean Item cart record ID if item exists in cart, False if not
     */
    public function Contains($item_id, $extras=array())
    {
        $id_parts = SHOP_explode_opts($item_id, true);

        if (!isset($id_parts[1])) $id_parts[1] = '';
        $args = array(
            'product_id'    => $id_parts[0],
            'options'       => $id_parts[1],
            'extras'        => $extras,
        );
        $Item2 = new OrderItem($args);
        foreach ($this->items as $id=>$Item1) {
            if ($Item1->Matches($Item2)) {
                return $id;
            }
        }
        // No matching item_id found
        return false;
    }


    /**
     * Get the requested address array.
     * Converts internal vars named 'billto_name', etc. to an array keyed by
     * the base field namee 'name', 'address1', etc. The result can be passed
     * to the Address class.
     *
     * @param   string  $type   Type of address, billing or shipping
     * @return  array           Array of name=>value address elements
     */
    public function getAddress($type)
    {
        if ($type != 'billto') {
            $type = 'shipto';
        }
        $fields = array();
        foreach ($this->_addr_fields as $name) {
            $var = $type . '_' . $name;
            $fields[$name] = $this->$var;
        }
        return $fields;
    }


    /**
     * Get the cart info from the private m_info array.
     * If no key is specified, the entire m_info array is returned.
     * If a key is specified but not found, the NULL is returned.
     *
     * @param   string  $key    Specific item to return
     * @return  mixed       Value of item, or entire info array
     */
    public function getInfo($key = '')
    {
        if ($key != '') {
            if (isset($this->m_info[$key])) {
                return $this->m_info[$key];
            } else {
                return NULL;
            }
        } else {
            return $this->m_info;
        }
    }


    /**
     * Get all the items in this order
     *
     * @return  array   Array of OrderItem objects
     */
    public function getItems()
    {
        return $this->items;
    }


    /**
     * Set an info item into the private info array.
     *
     * @param   string  $key    Name of var to set
     * @param   mixed   $value  Value to set
     * @return  object      Current Order object
     */
    public function setInfo($key, $value)
    {
        $this->m_info[$key] = $value;
        return $this;
    }


    /**
     * Remove an information item from the private info array.
     *
     * @param   string  $key    Name of var to remove
     */
    public function remInfo($key)
    {
        unset($this->m_info[$key]);
        return $this;
    }


    /**
     * Get the gift card amount applied to this cart.
     *
     * @return  float   Gift card amount
     */
    public function getGC()
    {
        return (float)$this->getInfo('apply_gc');
    }


    /**
     * Apply a gift card amount to this cart.
     *
     * @param   float   $amt    Amount of credit to apply
     * @return  object      Current Order object
     */
    public function setGC($amt)
    {
        $amt = (float)$amt;
        if ($amt == -1) {
            $gc_bal = \Shop\Products\Coupon::getUserBalance();
            $amt = min($gc_bal, \Shop\Products\Coupon::canPayByGC($this));
        }
        $this->setInfo('apply_gc', $amt);
        return $this;
    }


    /**
     * Set the chosen payment gateway into the cart information.
     * Used so the gateway will be pre-selected if the buyer returns to the
     * cart update page.
     *
     * @param   string  $gw_name    Gateway name
     * @return  object      Current Order object
     */
    public function setGateway($gw_name)
    {
        $this->setInfo('gateway', $gw_name);
        return $this;
    }


    /**
     * Check if this order has any physical items.
     * Used to adapt workflows based on product types.
     *
     * @return  integer     Number of physical items x quantity
     */
    public function hasPhysical()
    {
        $retval = 0;
        foreach ($this->items as $id=>$item) {
            if ($item->getProduct()->isPhysical()) {
                $retval += $item->getQuantity();
            }
        }
        return $retval;
    }


    /**
     * Check if this order has only downloadable items.
     *
     * @return  boolean     True if download only, False if now.
     */
    public function isDownloadOnly()
    {
        foreach ($this->items as $id=>$item) {
            if (!$item->getProduct()->isDownload(true)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Check if this order is paid.
     * The status may be one of several values like "shipped", "closed", etc.
     * but should not be "cart" or "pending".
     *
     * @return  boolean     True if not a cart or pending order, false otherwise
     */
    public function isPaid()
    {
        switch ($this->status) {
        case 'cart':
        case 'pending':
            return false;
        default:
            return true;
        }
    }


    /**
     * Get shipping information for the items to use when selecting a shipper.
     *
     * @return  array   Array('units'=>unit_count, 'amount'=> fixed per-item amount)
     */
    public function getItemShipping()
    {
        $shipping_amt = 0;
        $shipping_units = 0;
        foreach ($this->items as $item) {
            $shipping_amt += $item->getShippingAmt();
            $shipping_units += $item->getShippingUnits();
        }
        return array(
            'units' => $shipping_units,
            'amount' => $shipping_amt,
        );
    }


    /**
     * Set the buyer email to the supplied email address.
     * First checks that the supplied address is a valid one.
     *
     * @param   string  $email  Email address
     * @return  object      Current Order object
     */
    public function setEmail($email)
    {
        if (COM_isEmail($email)) {
            $this->buyer_email = $email;
        }
        return $this;
    }


    /**
     * Set shipper information in the info array, including the best rate.
     *
     * @param   integer $shipper_id     Shipper record ID
     * @return  object      Current Order object
     */
    public function setShipper($shipper_id)
    {
        if ($this->hasPhysical()) {
            $shippers = Shipper::getShippersForOrder($this);
            // Have to iterate through all the shippers since the array is
            // ordered by rate, not shipper ID
            foreach ($shippers as $sh) {
                if ($sh->id == $shipper_id) {
                    $this->shipping = $sh->ordershipping->total_rate;
                    $this->shipper_id = $sh->id;
                    break;
                }
            }
        } else {
            $this->shipping = 0;
            $this->shipper_id = 0;
        }
        return $this;
    }


    /**
     * Select the shipping method for this order.
     * Displays a list of shippers with the rates for each
     * @todo    1. Sort by rate DONE
     *          2. Save shipper selection with the order
     *
     *  @param  integer $step   Current step in workflow
     *  @return string      HTML for shipper selection form
     */
    public function selectShipper()
    {
        if (!$this->hasPhysical()) {
            return '';
        }

        // Get all the shippers and rates for the selection
        $shippers = Shipper::getShippersForOrder($this);
        if (empty($shippers)) return '';

        // Get the best or previously-selected shipper for the default choice
        $best = NULL;
        $shipper_id = $this->shipper_id;
        if ($shipper_id !== NULL) {
            // Array is 0-indexed so search for the shipper ID, if any.
            foreach ($shippers as $id=>$shipper) {
                if ($shipper->id == $shipper_id) {
                    // Already have a shipper selected
                    $best = $shippers[$id];
                    break;
                }
            }
        }
        if ($best === NULL) {
            // None already selected, grab the first one. It has the best rate.
            $best = reset($shippers);
        }

        $T = SHOP_getTemplate('shipping_method', 'form');
        $T->set_block('form', 'shipMethodSelect', 'row');

        // Save the base charge (order total - current shipping charge).
        $base_chg = $this->subtotal + $this->handling + $this->tax;
        $ship_rates = array();
        foreach ($shippers as $shipper) {
            $sel = $shipper->id == $best->id ? 'selected="selected"' : '';
            $s_amt = $shipper->ordershipping->total_rate;
            $rate = array(
                'amount'    => (string)Currency::getInstance()->FormatValue($s_amt),
                'total'     => (string)Currency::getInstance()->FormatValue($base_chg + $s_amt),
            );
            $ship_rates[$shipper->id] = $rate;
            $T->set_var(array(
                'method_sel'    => $sel,
                'method_name'   => $shipper->name,
                'method_rate'   => Currency::getInstance()->Format($s_amt),
                'method_id'     => $shipper->id,
                'order_id'      => $this->order_id,
                'multi'         => count($shippers) > 1 ? true : false,
            ) );
            $T->parse('row', 'shipMethodSelect', true);
        }
        $T->set_var('shipper_json', json_encode($ship_rates));
        $T->parse('output', 'form');
        return  $T->finish($T->get_var('output'));
    }


    /**
     * Set all the billing and shipping address vars into the template.
     *
     * @param   object  $T      Template object
     */
    private function _setAddressTemplate(&$T)
    {
        // Set flags in the template to indicate which address blocks are
        // to be shown.
        foreach (Workflow::getAll($this) as $key => $wf) {
            $T->set_var('have_' . $wf->wf_name, 'true');
        }
        foreach (array('billto', 'shipto') as $type) {
            foreach ($this->_addr_fields as $name) {
                $fldname = $type . '_' . $name;
                $T->set_var($fldname, $this->$fldname);
            }
        }
    }


    /**
     * Determine if an order is final, that is, cannot be updated or deleted.
     *
     * @param   string  $status     Status to check, if not the current status
     * @return  boolean     True if order is final, False if still a cart or pending
     */
    public function isFinal($status = NULL)
    {
        if ($status === NULL) {     // checking current status
            $status = $this->status;
        }
        return !in_array($status, self::$nonfinal_statuses);
    }


    /**
     * Convert from one currency to another.
     *
     * @param   string  $new    New currency, configured currency by default
     * @param   string  $old    Original currency, $this->currency by default
     * @return  object      Current Order object
     */
    public function convertCurrency($new ='', $old='')
    {
        global $_SHOP_CONF;

        if ($new == '') $new = $_SHOP_CONF['currency'];
        if ($old == '') $old = $this->currency;
        // If already set, return OK. Nothing to do.
        if ($new != $old) {
            // Update each item's pricing
            foreach ($this->items as $Item) {
                $Item->convertCurrency($old, $new);
            }

            // Update the currency amounts stored with the order
            foreach (array('tax', 'shipping', 'handling') as $fld) {
                $this->$fld = Currency::Convert($this->$fld, $new, $old);
            }

            // Set the order's currency code to the new value and save.
            $this->currency = $new;
            $this->Save();
        }
        return true;
    }


    /**
     * Provide a central location to get the URL to print or view a single order.
     *
     * @param   string  $view   View type (order or print)
     * @return  string      URL to the view/print page
     */
    public function buildUrl($view)
    {
        return COM_buildUrl(SHOP_URL . "/order.php?mode=$view&id={$this->order_id}&token={$this->token}");
    }


    /**
     * Check if there are any non-cart orders or IPN messages in the database.
     * Used to determine if data can be migrated from Paypal.
     *
     * @return  boolean     True if orders table is empty
     */
    public static function haveOrders()
    {
        global $_TABLES;

        return (
            (int)DB_getItem(
                $_TABLES['shop.orders'],
                'count(*)',
                "status <> 'cart'"
            ) > 0 ||
            IPN::Count() > 0
        );
    }


    /**
     * Get the base language name from the full string contained in the user record.
     * Wrapper for Customer::getLanguage().
     *
     * @see     Customer::getLanguage()
     * @param   boolean $fullname   True to return full name of language
     * @return  string  Language name for the buyer.
     */
    private function _getLangName($fullname = false)
    {
        $Cust = Customer::getInstance($this->uid);
        return $Cust->getLanguage($fullname);
    }


    /**
     * Loads the requested language array to send email in the recipient's language.
     * If $requested is an array, the first valid language file is loaded.
     * If not, the $requested language file is loaded.
     * If $requested doesn't refer to a vailid language, then $_CONF['language']
     * is assumed.
     *
     * After loading the base language file, the same filename is loaded from
     * language/custom, if available. The admin can override language strings
     * by creating a language file in that directory.
     *
     * @param   mixed   $requested  A single or array of language strings
     * @return  array       $LANG_SHOP, the global language array for the plugin
     */
    public static function loadLanguage($requested)
    {
        global $_CONF;

        // Add the requested language, which may be an array or
        // a single item.
        if (is_array($requested)) {
            $languages = $requested;
        } else {
            // If no language requested, load the site/user default
            $languages = array($requested);
        }

        // Add the site language as a failsafe
        $languages[] = $_CONF['language'];

        // Final failsafe, include "english.php" which is known to exist
        $languages[] = 'english';

        // Search the array for desired language files, in order.
        $langpath = SHOP_PI_PATH . '/language';
        foreach ($languages as $language) {
            if (file_exists("$langpath/$language.php")) {
                include "$langpath/$language.php";
                // Include admin-supplied overrides, if any.
                if (file_exists("$langpath/custom/$language.php")) {
                    include "$langpath/custom/$language.php";
                }
                break;
            }
        }
        return $LANG_SHOP;
    }


    /**
     * Get the total quantity of items with the same base item ID.
     * Used to calculate prices where discounts apply.
     * Similar to self::Contains() but this only considers the base item ID
     * and ignores option selections rather than looking for an exact match.
     *
     * @param   mixed   $item_id    Item ID to check
     * @return  float       Total quantity of items on the order.
     */
    public function getTotalBaseItems($item_id)
    {
        static $qty = array();

        // Extract the item ID if options were included in the parameter
        $x = explode('|', $item_id);
        $item_id = $x[0];
        if (!isset($qty[$item_id])) {
            $qty[$item_id] = 0;
            foreach ($this->items as $item) {
                if ($item->getProductId() == $item_id) {
                    $qty[$item_id] += $item->getQuantity();
                }
            }
        }
        return $qty[$item_id];
    }


    /**
     * Apply quantity discounts to all like items on the order.
     * This allows all items of the same product to be considered for the
     * discount regardless of options chosen.
     * If the return value is true then the cart/order should be saved. False
     * is returned if there were no changes.
     *
     * @param   mixed   $item_id    Base Item ID
     * @return  boolean     True if any prices were changed, False if not.
     */
    public function applyQtyDiscounts($item_id)
    {
        $have_changes = false;
        $x = explode('|', $item_id);
        $item_id = $x[0];

        // Get the product item and see if it has any quantity discounts.
        // If not, just return.
        $P = Product::getByID($item_id);
        if (!$P->hasDiscounts()) {
            return false;
        }

        $total_qty = $this->getTotalBaseItems($item_id);
        foreach ($this->items as $key=>$OI) {
            if ($OI->product_id != $item_id) continue;
            $qty_discount = $P->getDiscount($total_qty);
            $new_price = $P->getDiscountedPrice($total_qty, $OI->getOptionsPrice());
            $OI->setPrice($new_price);
            $OI->setDiscount($qty_discount);
            $OI->Save();
        }
        //Cache::deleteOrder($this->order_id);
        return true;
    }


    /**
     * Purge all orders from the database.
     * No safety check or confirmation is done; that should be done before
     * calling this function.
     */
    public static function Purge()
    {
        global $_TABLES;

        DB_query("TRUNCATE {$_TABLES['shop.orders']}");
        DB_query("TRUNCATE {$_TABLES['shop.orderitems']}");
        DB_query("TRUNCATE {$_TABLES['shop.order_log']}");
    }


    /**
     * Create the complete tag to link to the packing list for this order.
     *
     * @param   string  $order_id   Order ID
     * @param   string  $target     Target, defaule = "_blank"
     * @return  string      Complete tag
     */
    public static function linkPackingList($order_id, $target='_blank')
    {
        global $LANG_SHOP;

        return COM_createLink(
            '<i class="uk-icon-mini uk-icon-list"></i>',
           SHOP_ADMIN_URL . '/report.php?pdfpl=' . $order_id,
            array(
                'class' => 'tooltip',
                'title' => $LANG_SHOP['packinglist'],
                'target' => $target,
            )
        );
    }


    /**
     * Create the complete tag to link to the print view of this order.
     *
     * @param   string  $order_id   Order ID
     * @param   string  $token      Access token
     * @param   string  $target     Target, defaule = "_blank"
     * @return  string      Complete tag
     */
    public static function linkPrint($order_id, $token='', $target = '_blank')
    {
        global $LANG_SHOP;

        $url = SHOP_URL . '/order.php?mode=pdforder&id=' . $order_id;
        if ($token != '') $url .= '&token=' . $token;
        return COM_createLink(
            '<i class="uk-icon-mini uk-icon-print"></i>',
            COM_buildUrl($url),
            array(
                'class' => 'tooltip',
                'title' => $LANG_SHOP['print'],
                'target' => $target,
            )
        );
    }


    /**
     * Get the total shipping units for this order.
     * Called from the Shipper class when calculating shipping options.
     *
     * @return  float   Total shipping units
     */
    public function totalShippingUnits()
    {
        $units = 0;
        foreach ($this->items as $item) {
            $P = $item->getProduct();
            if ($P->isPhysical()) {
                $units += $P->getShippingUnits() * $item->getQuantity();
            }
        }
        return $units;
    }


    /**
     * Create PDF output of one or more orders.
     *
     * @param   array   $ids    Array of order IDs
     * @param   string  $type   View type, 'pl' or 'order'
     * @param   boolean $isAdmin    True if run by an administrator
     * @return  boolean     True on success, False on error
     */
    public static function printPDF($ids, $type='pdfpl', $isAdmin = false)
    {
        USES_lglib_class_html2pdf();
        try {
            if (class_exists('\\Spipu\\Html2Pdf\\Html2Pdf')) {
                $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'en');
            } else {
                $html2pdf = new \HTML2PDF('P', 'A4', 'en');
            }
            //$html2pdf->setModeDebug();
            $html2pdf->setDefaultFont('Arial');
        } catch(HTML2PDF_exception $e) {
            COM_errorLog($e);
            return false;
        }

        if (!is_array($ids)) {
            $ids = array($ids);
        }
        foreach ($ids as $ord_id) {
            $O = self::getInstance($ord_id);
            $O->setAdmin($isAdmin);
            if ($O->isNew) {
                continue;
            }
            $content = $O->View($type);
            //echo $content;die;
            try {
                $html2pdf->writeHTML($content);
            } catch(HTML2PDF_exception $e) {
                COM_errorLog($e);
                return false;
            }
        }
        $html2pdf->Output($type . 'list.pdf', 'I');
        return true;
    }


    /**
     * Get the order date.
     *
     * @return  object  Date object
     */
    public function getOrderDate()
    {
        return $this->order_date;
    }


    /**
     * Get the Billing address.
     *
     * @return  object  Address object with billing information
     */
    public function getBillto()
    {
        return $this->Billto;
    }


    /**
     * Get the Shipping address.
     *
     * @return  object  Address object with shipping information
     */
    public function getShipto()
    {
        return $this->Shipto;
    }


    /**
     * Get the shipping info block for display on order views.
     *
     * @return  string      HTML for shipping info block
     */
    public function getShipmentBlock()
    {
        global $_CONF;

        $Shipments = Shipment::getByOrder($this->order_id);
        if (empty($Shipments)) {
            return '';
        }
        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file('html', 'shipping_block.thtml');
        $T->set_block('html', 'Packages', 'packages');
        foreach ($Shipments as $Shipment) {
            $Dt = new \Date($Shipment->ts, $_CONF['timezone']);
            $Packages = $Shipment->getPackages();
            if (empty($Packages)) {
                // Create a dummy package so something shows for the shipment
                $Packages[]= new ShipmentPackage();
            }
            $show_ship_info = true;
            foreach ($Packages as $Pkg) {
                $url = Shipper::getInstance($Pkg->shipper_id)->getTrackingUrl($Pkg->tracking_num);
                $Sh = Shipper::getInstance($Pkg->shipper_id);
                $T->set_var(array(
                    'show_ship_info' => $show_ship_info,
                    'ship_date'     => $Dt->toMySQL(true),
                    'shipment_id'   => $Shipment->shipment_id,
                    'shipper_info'  => $Pkg->shipper_info,
                    'tracking_num'  => $Pkg->tracking_num,
                    'shipper_id'    => $Pkg->shipper_id,
                    'tracking_url'  => $url,
                    'ret_url'       => urlencode($_SERVER['REQUEST_URI']),
                ) );
                $show_ship_info = false;
                $T->parse('packages', 'Packages', true);
            }
        }
        $T->parse('output', 'html');
        $html = $T->finish($T->get_var('output'));
        return $html;
    }


    /**
     * Get the token assigned to this order.
     *
     * @return  string  Token string
     */
    public function getToken()
    {
        return $this->token;
    }


    /**
     * Get all shipment objects related to this order.
     *
     * @return  array   Array of Shipment objects
     */
    public function getShipments()
    {
        return Shipment::getByOrder($this->order_id);
    }


    /**
     * Get the total number of items yet to be shipped.
     * Only considers physical products.
     *
     * @return  integer     Total items (quantitity) to be shipped
     */
    public function itemsToShip()
    {
        $total_items = 0;
        $shipped_items = 0;
        foreach ($this->items as $oi_id=>$data) {
            if ($data->getProduct()->isPhysical()) {
                $total_items += $data->quantity;
                $shipped_items += ShipmentItem::getItemsShipped($oi_id);
            }
        }
        return ($total_items - $shipped_items);
    }


    /**
     * Check if this order has been completely shipped.
     *
     * @return  boolean     True if no further shipment is needed
     */
    public function isShippedComplete()
    {
        $shipped = array();
        $Shipments = $this->getShipments();
        foreach ($Shipments as $Shipment) {
            foreach ($Shipment->getItems() as $SI) {
                if (!isset($shipped[$SI->orderitem_id])) {
                    $shipped[$SI->orderitem_id] = $SI->quantity;
                } else {
                    $shipped[$SI->orderitem_id] += $SI->quantity;
                }
            }
        }
        foreach ($this->getItems() as $OI) {
            if (!$OI->getProduct()->isPhysical()) {
                continue;
            }
            if (!isset($shipped[$OI->id]) || $shipped[$OI->id] < $OI->quantity) {
                return false;
            }
        }
        return true;
    }


    /**
     * Set the order status to a new value.
     *
     * @param   string  $newstatus  New status to set
     * @return  object  $this
     */
    public function setStatus($newstatus)
    {
        $this->status = $newstatus;
        return $this;
    }

}

?>
