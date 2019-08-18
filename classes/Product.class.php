<?php
/**
 * Class to manage products.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Class for product.
 * @package shop
 */
class Product
{
    /** Minimum possible date available.
     * @const string */
    const MIN_DATE = '1900-01-01';

    /** Maximum possible date available.
     * @const string */
    const MAX_DATE = '9999-12-31';

    /** Out-of-stock items can be sold and backordered.
     * @const integer */
    const OVERSELL_ALLOW = 0;

    /** Out-of-stock items appear in the catalot but can't be sold.
     * @const integer */
    const OVERSELL_DENY = 1;

    /** Out-of-stock items are hidden from the catalog and can't be sold.
     * @const integer */
    const OVERSELL_HIDE = 2;

    /** Property fields accessed via `__set()` and `__get()`.
     * @var array */
    protected $properties;

    /** Product Attributes.
     * @var array */
    public $options;

    /** Indicate whether the current user is an administrator.
     * @var boolean */
    public $isAdmin;

    /** Indicate that this is a new record.
     * @var boolean */
    public $isNew;

    /** Array of error messages/
     * @var array */
    var $Errors = array();

    /** Array of buttons.
     * @var array */
    public $buttons;

    /** Special fields, created by adding text strings.
     * @var array */
    protected $special_fields = array();

    /** Fixed quantity that can be purchased, zero means use-selectable.
     * @var integer */
    protected $_fixed_q = 0;

    /** Category object related to this product.
     * @var object */
    public $Cat;

    /** Indicate that the price can be overridden during purchase.
     * Typically used by plugin items.
     * @var boolean */
    public $override_price = false;

    /** User ID, used for pricing.
     * @var integer */
    private $_uid = 0;

    /** Type of button to create depends on the current view- list or detail.
     * @var string */
    private $_view = 'detail';

    /** Sale object associated with this product.
     * @var object */
    private $Sale = NULL;

    /** Product image objects.
     * @var array */
    private $Images = NULL;

    /**
     * Constructor.
     * Reads in the specified class, if $id is set.  If $id is zero,
     * then a new entry is being created.
     *
     * @param   integer $id Optional type ID
     */
    public function __construct($id=0)
    {
        global $_SHOP_CONF;

        $this->properties = array();
        $this->isNew = true;
        $this->pi_name = $_SHOP_CONF['pi_name'];
        $this->btn_text = '';
        $this->cancel_url = SHOP_URL . '/index.php';

        if (is_array($id)) {
            $this->setVars($id, true);
            $this->isNew = false;
            $this->Cat = Category::getInstance($this->cat_id);
        } elseif ($id == 0) {
            $this->item_id = '';
            $this->id = 0;
            $this->name = '';
            $this->cat_id = '';
            $this->short_description = '';
            $this->description = '';
            $this->price = 0;
            $this->prod_type = SHOP_PROD_VIRTUAL;
            $this->weight = 0;
            $this->file = '';
            $this->expiration = $_SHOP_CONF['def_expiration'];
            $this->enabled = $_SHOP_CONF['def_enabled'];
            $this->featured = $_SHOP_CONF['def_featured'];
            $this->taxable = $_SHOP_CONF['def_taxable'];
            $this->dt_add = SHOP_now()->toMySQL();
            $this->views = 0;
            $this->rating = 0;
            $this->votes = 0;
            $this->shipping_type = 0;
            $this->shipping_amt = 0;
            $this->shipping_units = 0;
            $this->show_random = 1;
            $this->show_popular = 1;
            $this->keywords = '';
            $this->comments_enabled = $_SHOP_CONF['ena_comments'] == 1 ?
                    SHOP_COMMENTS_ENABLED : SHOP_COMMENTS_DISABLED;
            $this->rating_enabled = $_SHOP_CONF['ena_ratings'] == 1 ? 1 : 0;
            $this->track_onhand = $_SHOP_CONF['def_track_onhand'];
            $this->oversell = $_SHOP_CONF['def_oversell'];
            $this->qty_discounts = array();
            $this->custom = '';
            $this->Cat = NULL;
        } else {
            $this->id = $id;
            if (!$this->Read()) {
                $this->id = 0;
            }
        }
        if ($this->id > 0) {
            $this->getImages();
        }
        $this->isAdmin = plugin_ismoderator_shop() ? 1 : 0;
    }


    /**
     * Gets an instance of a product object.
     * Figures out the type of product (plugin, catalog, etc.)
     * and instantiates an object if necessary.
     * $A can be a single item id or an array (DB record) of values.
     *
     * @param   mixed   $A      Single item ID or array of values
     * @param   array   $mods   Optional array of product modifiers
     * @return  object          Product Object
     */
    public static function getInstance($A, $mods=array())
    {
        global $_SHOP_CONF, $_TABLES;
        static $P = array();    // cache for internal products

        if (is_array($A) && isset($A['id'])) {
            // A complete product record
            return self::_getInstance($A);
            //$id = isset($A['id']) ? $A['id'] : NULL;
        } else {
            // A single product ID
            $id = $A;
        }
        if (!$id) {
            // Missing product ID
            return NULL;
        }

        $item = explode('|', $id);
        if (self::isPluginItem($item[0])) {
            // Product provided by another plugin
            return new \Shop\Products\Plugin($item[0], $mods);
        } else {
            if (!array_key_exists($id, $P)) {
                // Product internal to this plugin
                if ($_SHOP_CONF['use_sku']) {
                    $P[$id] = self::getBySKU($item[0]);
                } else {
                    $P[$id] = self::getByID($item[0]);
                }

                /*if (!is_array($A)) {
                    $cache_key = self::_makeCacheKey($item[0]);
                    //Cache::delete($cache_key);
                    $A = Cache::get($cache_key);
                    if (!is_array($A)) {
                        // If not found in cache
                        if ($_SHOP_CONF['use_sku']) {
                            $key = 'name';
                            $id = DB_escapeString($item[0]);
                        } else {
                            $key = 'id';
                            $id = (int)$item[0];
                        }
                        $sql = "SELECT * FROM {$_TABLES['shop.products']}
                                WHERE $key = '$id'";
                        $res = DB_query($sql);
                        $A = DB_fetchArray($res, false);
                        if (isset($A['id'])) {
                            Cache::set($cache_key, $A, array('products'));
                        }
                    }
                }*/
                //$P[$id] = self::_getInstance($A);
                /*if (isset($A['prod_type']) && $A['prod_type'] == SHOP_PROD_COUPON) {
                    $P[$id] = new \Shop\Products\Coupon($A);
                } else {
                    $P[$id] = new self($A);
                }
                if ($P[$id]->hasAccess()) {
                    $P[$id]->loadAttributes();
                }*/
            }
            return $P[$id];
        }
    }


    /**
     * Instantiate an objec from a DB record array.
     *
     * @param   array   $A  DB record
     * @return  object      Product object
     */
    private static function _getInstance($A)
    {
        if (isset($A['prod_type']) && $A['prod_type'] == SHOP_PROD_COUPON) {
            $P = new \Shop\Products\Coupon($A);
        } else {
            $P = new self($A);
        }
        if ($P->hasAccess()) {
            $P->loadAttributes();
        }
        return $P;
    }


    /**
     * Get an item by its SKU.
     *
     * @param   string  $id SKU to locate
     * @return  object      Product object
     */
    public static function getBySKU($id)
    {
        global $_TABLES;

        $parts = explode('-', $id);
        $item_id = DB_escapeString($parts[0]);
        $cache_key = self::_makeCacheKey($item_id);
        $A = Cache::get($cache_key);
        if (!is_array($A)) {
            $sql = "SELECT * FROM {$_TABLES['shop.products']}
                    WHERE name = '$item_id'";
            $res = DB_query($sql);
            $A = DB_fetchArray($res, false);
            if (isset($A['id'])) {
                Cache::set($cache_key, $A, array('products'));
            }
        }
        return self::_getInstance($A);
    }


    /**
     * Get a product by the database ID
     *
     * @param   integer $id     Item ID
     * @return  object      Product object
     */
    public static function getByID($id)
    {
        global $_TABLES;

        $parts = explode('|', $id);
        // Have to handle possible plugin items here as well
        if (self::isPluginItem($parts[0])) {
            // Product provided by another plugin
            return self::getInstance($parts[0]);
        } else {
            $id = (int)$parts[0];
            $cache_key = self::_makeCacheKey($id);
            $A = Cache::get($cache_key);
            if (!is_array($A)) {
                $sql = "SELECT * FROM {$_TABLES['shop.products']}
                        WHERE id  = '$id'";
                $res = DB_query($sql);
                $A = DB_fetchArray($res, false);
                if (isset($A['id'])) {
                    Cache::set($cache_key, $A, array('products'));
                }
            }
            return self::_getInstance($A);
        }
    }


    /**
     * Set a property's value.
     *
     * @param   string  $var    Name of property to set.
     * @param   mixed   $value  New value for property.
     */
    public function __set($var, $value)
    {
        switch ($var) {
        case 'views':
        case 'votes':
        case 'prod_type':
        case 'cat_id':
        case 'shipping_type':
        case 'comments_enabled':
        case 'onhand':
        case 'oversell':
        case 'expiration':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'price':
        case 'rating':
        case 'weight':
        case 'shipping_amt':
        case 'shipping_units':
        case '_act_price':      // actual price, sale or nonsale
        case '_orig_price':     // original price
            // Float values
            $this->properties[$var] = (float)$value;
            break;

        case 'avail_end':
            // available to end of time by default
            if (empty($value) || $value == '0000-00-00')
                $value = self::MAX_DATE;
            $this->properties[$var] = trim($value);
            break;

        case 'avail_beg':
            // sale dates and beginning availability default to 0000-00-00
            if (empty($value)) $value = self::MIN_DATE;
            $this->properties[$var] = trim($value);
            break;

        case 'dt_add':
        case 'description':
        case 'short_description':
        case 'file':
        case 'keywords':
        case 'btn_type':
        case 'item_id':
        case 'btn_text':
        case 'cancel_url':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'name':
        case 'old_sku':
            $this->properties[$var] = trim(preg_replace("/[^A-Za-z0-9 ]/", '', $value));
            break;

        case 'enabled':
        case 'featured':
        case 'taxable':
        case 'show_random':
        case 'show_popular':
        case 'rating_enabled':
        case 'track_onhand':
            // Boolean values
            $this->properties[$var] = $value == 1 ? 1 : 0;
            break;

        case 'categories':
            // Saving the category, or category list
            if (!is_array($value)) {
                $value = array($value);
            }
            $this->properties[$var] = $value;
            break;

        case 'qty_discounts':
            if (!is_array($value)) {
                $value = @unserialize($value);
                if ($value === false) $value = array();
            }
            ksort($value);
            $this->properties[$var] = $value;
            break;

        case 'id':
            // Item ID may be a string if this is a plugin,
            // otherwise sanitize as an integer.
            if (!self::isPluginItem($value)) {
                $value = (int)$value;
            }

        default:
            // Other value types (array?). Save it as-is.
            $this->properties[$var] = $value;
            break;
        }
    }


    /**
     * Get the value of a property.
     *
     * @param   string  $var    Name of property to retrieve.
     * @return  mixed           Value of property, NULL if undefined.
     */
    public function __get($var)
    {
        if (array_key_exists($var, $this->properties)) {
            return $this->properties[$var];
        } else {
            return NULL;
        }
    }


    /**
     * Sets all variables to the matching values from $rows.
     *
     * @param   array   $row        Array of values, from DB or $_POST
     * @param   boolean $fromDB     True if read from DB, false if from $_POST
     */
    public function setVars($row, $fromDB=false)
    {
        if (!is_array($row)) return;

        $this->id = $row['id'];
        $this->item_id = $row['id'];
        $this->description = $row['description'];
        $this->enabled = isset($row['enabled']) ? $row['enabled'] : 0;
        $this->featured = isset($row['featured']) ? $row['featured'] : 0;
        $this->name = $row['name'];
        $this->old_sku = SHOP_getVar($row, 'old_sku');
        $this->cat_id = $row['cat_id'];
        $this->short_description = $row['short_description'];
        $this->price = $row['price'];
        $this->file = $row['file'];
        $this->expiration = $row['expiration'];
        $this->keywords = $row['keywords'];
        $this->prod_type = isset($row['prod_type']) ? $row['prod_type'] : 0;
        $this->weight = $row['weight'];
        $this->taxable = isset($row['taxable']) ? $row['taxable'] : 0;
        $this->shipping_type = SHOP_getVar($row, 'shipping_type', 'integer');
        $this->shipping_amt = SHOP_getVar($row, 'shipping_amt', 'float');
        $this->shipping_units = SHOP_getVar($row, 'shipping_units', 'float');
        $this->show_random = isset($row['show_random']) ? $row['show_random'] : 0;
        $this->show_popular = isset($row['show_popular']) ? $row['show_popular'] : 0;
        $this->track_onhand = isset($row['track_onhand']) ? $row['track_onhand'] : 0;
        $this->onhand = $row['onhand'];
        $this->oversell = isset($row['oversell']) ? $row['oversell'] : 0;
        $this->custom = $row['custom'];
        $this->avail_beg = $row['avail_beg'];
        $this->avail_end = $row['avail_end'];

        // Get the quantity discount table. If coming from a form,
        // there will be two array variables for qty and discount percent.
        // From the DB there's a single serialized string
        if ($fromDB) {
            // unserialization happens in __set()
            $this->qty_discounts = $row['qty_discounts'];
            $this->dt_add = $row['dt_add'];
        } else {
            $this->dt_add = SHOP_now()->toMySQL();
            $qty_discounts = array();
            for ($i = 0; $i < count($row['disc_qty']); $i++) {
                $disc_qty = (int)$row['disc_qty'][$i];
                if ($disc_qty < 1) continue;
                if (isset($row['disc_amt'][$i])) {
                    $qty_discounts[$disc_qty] = abs($row['disc_amt'][$i]);
                }
            }
            $this->qty_discounts = $qty_discounts;
        }

        if (isset($row['categories'])) {
            $this->categories = $row['categories'];
        } else {
            $this->categories = array();
        }

        $this->votes = isset($row['votes']) ? $row['votes'] : 0;
        $this->rating = isset($row['rating']) ? $row['rating'] : 0;
        $this->comments_enabled = $row['comments_enabled'];
        $this->rating_enabled = isset($row['rating_enabled']) ? $row['rating_enabled'] : 0;
        $this->btn_type = $row['buttons'];
        if ($fromDB) {
            $this->views = $row['views'];
        }
    }


    /**
     * Read a specific record and populate the local values.
     *
     * @param   integer $id Optional ID.  Current ID is used if zero.
     * @return  boolean     True if a record was read, False on failure
     */
    public function Read($id = 0)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->id;
        if ($id == 0) {
            $this->error = 'Invalid ID in Read()';
            return false;
        }

        $cache_key = self::_makeCacheKey($id);
        $row = Cache::get($cache_key);
        if ($row === NULL) {
            $result = DB_query("SELECT *
                        FROM {$_TABLES['shop.products']}
                        WHERE id='$id'");
            if (!$result || DB_numRows($result) != 1) {
                return false;
            } else {
                $row = DB_fetchArray($result, false);
            }
        }
        if (!empty($row)) {
            $this->setVars($row, true);
            $this->isNew = false;
            $this->loadAttributes();
            return true;
        } else {
            return false;
        }
    }


    /**
     * Load the product attributs into the options array.
     */
    protected function loadAttributes()
    {
        global $_TABLES;

        $cache_key = self::_makeCacheKey($this->id, 'attr');
        $this->options = Cache::get($cache_key);
        if ($this->options === NULL) {
            $sql = "SELECT og.og_name, at.*
                FROM {$_TABLES['shop.prod_attr']} at
                LEFT JOIN {$_TABLES['shop.opt_grp']} og
                    ON og.og_id = at.og_id
                WHERE at.item_id = '{$this->id}' AND at.enabled = 1
                ORDER BY og.og_orderby, at.orderby ASC";
            $result = DB_query($sql);
            $this->options = array();
            while ($A = DB_fetchArray($result, false)) {
                $this->options[$A['attr_id']] = array(
                    'og_id'     => $A['og_id'],
                    'attr_name' => $A['og_name'],
                    'attr_value' => $A['attr_value'],
                    'attr_price' => $A['attr_price'],
                    'sku'       => $A['sku'],
                );
            }
            Cache::set($cache_key, $this->options, array('products', $this->id));
        }
    }


    /**
     * Save the current values to the database.
     * Does not save values from $this->Images.
     * Appends error messages to the $Errors property.
     *
     * @param   array   $A      Optional array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save($A = '')
    {
        global $_TABLES, $_SHOP_CONF, $LANG_SHOP;

        $old_rating_ena = $this->rating_enabled;    // save original setting

        if (is_array($A)) {
            $this->setVars($A);
        }

        $errs = $this->_Validate();
        if (!empty($errs)) {
            $this->Errors = $errs;
        }

        if (isset($A['delimg']) && is_array($A['delimg'])) {
            foreach ($A['delimg'] as $img_id) {
                $this->deleteImage($img_id);
            }
        }

        // Handle file uploads.
        // This is done first so we know whether there is a valid filename
        // for a download product.
        if ($this->isDownload()) {
            if (!empty($_FILES['uploadfile']['tmp_name'])) {
                $F = new File('uploadfile');
                $filename = $F->uploadFiles();
                if ($F->areErrors() > 0) {
                    $this->Errors[] = $F->printErrors(true);
                } elseif ($filename != '') {
                    $this->file = $filename;
                }
                SHOP_log('Uploaded file: ' . $this->file, SHOP_LOG_DEBUG);
            }
            if ($this->file == '') {
                // Not having a file is an error for downloadable products.
                $this->Errors[] = $LANG_SHOP['err_missing_file'];
            }
        } else {
            // Make sure file is empy for non-downloads.
            // May have previously contained a file if the type was changed.
            $this->file = '';
        }

        // For downloads and virtual items. physical options don't apply.
        if (!$this->isPhysical()) {
            $this->weight = 0;
            $this->shipping_type = 0;
            $this->shipping_amt = 0;
            $this->shipping_units = 0;
        }

        // If ratings were enabled but are now disabled, reset the rating
        // for this product.
        if ($old_rating_ena && !$this->rating_enabled) {
            RATING_resetRating($_SHOP_CONF['pi_name'], $this->id);
        }

        // Serialize the quantity discount array
        $qty_discounts = $this->qty_discounts;
        if (!is_array($qty_discounts)) $qty_discounts = array();
        $qty_discounts = DB_escapeString(@serialize($qty_discounts));

        // Insert or update the record, as appropriate
        if ($this->id > 0) {
            SHOP_log('Preparing to update product id ' . $this->id, SHOP_LOG_DEBUG);
            $sql1 = "UPDATE {$_TABLES['shop.products']} SET ";
            $sql3 = " WHERE id='{$this->id}'";
        } else {
            SHOP_log('Preparing to save a new product.', SHOP_LOG_DEBUG);
            $sql1 = "INSERT INTO {$_TABLES['shop.products']} SET
                dt_add = UTC_TIMESTAMP(), ";
            $sql3 = '';
        }
        //$options = DB_escapeString(@serialize($this->options));
        $sql2 = "name='" . DB_escapeString($this->name) . "',
                cat_id='" . (int)$this->cat_id . "',
                short_description='" . DB_escapeString($this->short_description) . "',
                description='" . DB_escapeString($this->description) . "',
                keywords='" . DB_escapeString($this->keywords) . "',
                price='" . number_format($this->price, 2, '.', '') . "',
                prod_type='" . (int)$this->prod_type. "',
                weight='" . number_format($this->weight, 2, '.', '') . "',
                file='" . DB_escapeString($this->file) . "',
                expiration='" . (int)$this->expiration. "',
                enabled='" . (int)$this->enabled. "',
                featured='" . (int)$this->featured. "',
                views='" . (int)$this->views. "',
                taxable='" . (int)$this->taxable . "',
                shipping_type='" . (int)$this->shipping_type . "',
                shipping_amt = '{$this->shipping_amt}',
                shipping_units = '{$this->shipping_units}',
                comments_enabled='" . (int)$this->comments_enabled . "',
                rating_enabled='" . (int)$this->rating_enabled . "',
                show_random='" . (int)$this->show_random . "',
                show_popular='" . (int)$this->show_popular . "',
                onhand='{$this->onhand}',
                track_onhand='{$this->track_onhand}',
                oversell = '{$this->oversell}',
                qty_discounts = '{$qty_discounts}',
                custom='" . DB_escapeString($this->custom) . "',
                avail_beg='" . DB_escapeString($this->avail_beg) . "',
                avail_end='" . DB_escapeString($this->avail_end) . "',
                buttons= '" . DB_escapeString($this->btn_type) . "'";
                //options='$options',
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql);
        if (!DB_error()) {
            if ($this->isNew) {
                $this->id = DB_insertID();
            }
            $status = true;
        } else {
            SHOP_log("Shop- SQL error in Product::Save: $sql", SHOP_LOG_ERROR);
            $status = false;
        }

        // Clear all product caches since this save may affect availablity
        // and product lists.
        Cache::clear('products');
        Cache::clear('sitemap');

        if ($status) {
            // Handle image uploads.  This is done last because we need
            // the product id to name the images filenames.
            if (!empty($_FILES['images'])) {
                $U = new ProductImage($this->id, 'images');
                $U->uploadFiles();

                if ($U->areErrors() > 0) {
                    $this->Errors = array_merge($this->Errors, $U->_errors);
                }
            }

            // Clear the button cache
            self::deleteButtons($this->id);
        }

        if (empty($this->Errors)) {
            COM_setMsg($LANG_SHOP['msg_updated']);
            SHOP_log('Update of product ' . $this->id . ' succeeded.', SHOP_LOG_DEBUG);
            PLG_itemSaved($this->id, $_SHOP_CONF['pi_name']);
            return true;
        } else {
            $msg = '<ul><li>' . implode('</li><li>', $this->Errors) . '</li></ul>';
            COM_setMsg($msg, 'error');
            SHOP_log('Update of product ' . $this->id . ' failed.', SHOP_LOG_ERROR);
            COM_refresh(SHOP_ADMIN_URL . '/index.php?editproduct=x&id=' . $this->id);
            return false;
        }
    }


    /**
     * Delete the current product record from the database.
     * Deletes the item, item attributes, images and buttons. Does not
     * update the purchases or IPN log at all. Does not delete an item
     * that has orders associated with it.
     *
     * @uses    self::deleteImage()
     * @uses    self::deleteButtons()
     * @return  boolean     True when deleted, False if invalid ID
     */
    public function Delete()
    {
        global $_TABLES, $_SHOP_CONF;

        if ($this->id <= 0 ||
            self::isUsed($this->id) ||
            self::isPluginItem($this->id)) {
            return false;
        }

        foreach ($this->Images as $prow) {
            $this->deleteImage($prow['img_id']);
        }
        DB_delete($_TABLES['shop.products'], 'id', $this->id);
        DB_delete($_TABLES['shop.prod_attr'], 'item_id', $this->id);
        self::deleteButtons($this->id);
        Cache::clear('products');
        Cache::clear('sitemap');
        PLG_itemDeleted($this->id, $_SHOP_CONF['pi_name']);
        $this->id = 0;
        $this->isNew = true;
        return true;
    }


    /**
     * Delete all buttons for a product.
     * Called when a product is updated so the buttons will be recreated
     * when needed.
     *
     * @param   integer $item_id    Product ID to delete
     */
    private static function deleteButtons($item_id)
    {
        global $_TABLES;

        DB_delete($_TABLES['shop.buttons'], 'item_id', $item_id);
    }


    /**
     * Deletes a single image from disk for the current product.
     *
     * @param   integer $img_id     DB ID of image to delete
     */
    public function deleteImage($img_id)
    {
        global $_TABLES, $_SHOP_CONF;

        $img_id = (int)$img_id;
        if ($img_id < 1 || !array_key_exists($img_id, $this->Images)) {
            return;
        }

        $filespec = $_SHOP_CONF['image_dir'] . DIRECTORY_SEPARATOR . $this->Images[$img_id]['filename'];
        if (is_file($filespec)) {
            // Ignore errors due to file permissions, etc. Worst case is
            // that an image gets left behind on disk
            @unlink($filespec);
        }

        DB_delete($_TABLES['shop.images'], 'img_id', $img_id);
        Cache::delete(self::_makeCacheKey($this->id));
        Cache::delete(self::_makeCacheKey($this->id, 'img'));
    }


    /**
     * Creates the product edit form.
     *
     * Creates the form for editing a product.  If a product ID is supplied,
     * then that product is read and becomes the current product.  If not,
     * then the current product is edited.  If an empty product was created,
     * then a new product is created here.
     *
     * @uses    SHOP_getDocUrl()
     * @uses    SHOP_errorMessage()
     * @param   integer $id     Optional ID, current record used if zero
     * @return  string          HTML for edit form
     */
    public function showForm($id = 0)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP;

        $id = (int)$id;
        if ($id > 0) {
            // If an id is passed in, then read that record
            if (!$this->Read($id)) {
                return SHOP_errorMessage($LANG_SHOP['invalid_product_id'], 'info');
            }
        }
        $id = $this->id;

        SEC_setCookie(
            $_CONF['cookie_name'].'adveditor',
            SEC_createTokenGeneral('advancededitor'),
            time() + 1200, $_CONF['cookie_path'],
            $_CONF['cookiedomain'],
            $_CONF['cookiesecure'],
            false
        );

        $T = SHOP_getTemplate('product_form', 'product');
        // Set up the wysiwyg editor, if available
        $tpl_var = $_SHOP_CONF['pi_name'] . '_entry';
        switch (PLG_getEditorType()) {
        case 'ckeditor':
            $T->set_var('show_htmleditor', true);
            PLG_requestEditor($_SHOP_CONF['pi_name'], $tpl_var, 'ckeditor_shop.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        case 'tinymce' :
            $T->set_var('show_htmleditor',true);
            PLG_requestEditor($_SHOP_CONF['pi_name'], $tpl_var, 'tinymce_shop.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        default :
            // don't support others right now
            $T->set_var('show_htmleditor', false);
            break;
        }

        // Add the current product ID to the form if it's an existing product.
        if ($id > 0) {
            $retval = COM_startBlock($LANG_SHOP['edit'] . ': ' . $this->name);

        } else {
            $T->set_var('id', '');
            $retval = COM_startBlock($LANG_SHOP['new_product']);

        }

        $T->set_var(array(
            //'post_options'  => $post_options,
            'product_id'    => $this->id,
            'old_sku'       => $this->name,
            'name'          => htmlspecialchars($this->name, ENT_QUOTES, COM_getEncodingt()),
            'category'      => $this->cat_id,
            'short_description' => htmlspecialchars($this->short_description, ENT_QUOTES, COM_getEncodingt()),
            'description'   => htmlspecialchars($this->description, ENT_QUOTES, COM_getEncodingt()),
            'price'         => Currency::getInstance()->FormatValue($this->price),
            'file'          => htmlspecialchars($this->file, ENT_QUOTES, COM_getEncodingt()),
            'expiration'    => $this->expiration,
            'action_url'    => SHOP_ADMIN_URL . '/index.php',
            'file_selection' => $this->FileSelector(),
            'keywords'      => htmlspecialchars($this->keywords, ENT_QUOTES, COM_getEncodingt()),
            'cat_select'    => Category::optionList($this->cat_id),
            'currency'      => $_SHOP_CONF['currency'],
            //'pi_url'        => SHOP_URL,
            'doc_url'       => SHOP_getDocURL('product_form',
                                            $_CONF['language']),
            'prod_type'     => $this->prod_type,
            'weight'        => $this->weight,
            'feat_chk'      => $this->featured == 1 ? 'checked="checked"' : '',
            'ena_chk'       => $this->enabled == 1 ? 'checked="checked"' : '',
            'tax_chk'       => $this->taxable == 1 ? 'checked="checked"' : '',
            'show_random_chk'  => $this->show_random == 1 ? 'checked="checked"' : '',
            'show_popular_chk' => $this->show_popular == 1 ?
                                    'checked="checked"' : '',
            'ship_sel_' . $this->shipping_type => 'selected="selected"',
            'shipping_type' => $this->shipping_type,
            'track_onhand'  => $this->track_onhand,
            'shipping_amt'  => Currency::getInstance()->FormatValue($this->shipping_amt),
            'shipping_units'  => $this->shipping_units,
            'sel_comment_' . $this->comments_enabled =>
                                    'selected="selected"',
            'rating_chk'    => $this->rating_enabled == 1 ?
                                    'checked="checked"' : '',
            'trk_onhand_chk' => $this->track_onhand== 1 ?
                                    'checked="checked"' : '',
            'onhand'        => $this->onhand,
            "oversell_sel{$this->oversell}" => 'selected="selected"',
            'custom' => $this->custom,
            'avail_beg'     => self::_InputDtFormat($this->avail_beg),
            'avail_end'     => self::_InputDtFormat($this->avail_end),
            'ret_url'       => SHOP_getUrl(SHOP_ADMIN_URL),
            //'limit_availability_chk' => $this->limit_availability ? 'checked="checked"' : '',
        ) );

        // Create the button type selections. New products get the default
        // button selected, existing products get the saved button selected
        // or "none" if there is no button.
        $T->set_block('product', 'BtnRow', 'BRow');
        $have_chk = false;
        foreach ($_SHOP_CONF['buttons'] as $key=>$checked) {
            if ($key == $this->btn_type || ($this->isNew && $checked)) {
                $btn_chk = 'checked="checked"';
                $have_chk = true;
            } else {
                $btn_chk = '';
            }
            $T->set_var(array(
                'btn_type'  => $key,
                'btn_chk'   => $key == $this->btn_type ||
                        ($this->isNew && $checked) ? 'checked="checked"' : '',
                'btn_name'  => $LANG_SHOP['buttons'][$key],
            ));
            $T->parse('BRow', 'BtnRow', true);
        }
        // Set the "none" selection if nothing was already selected
        $T->set_var('none_chk', $have_chk ? '' : 'checked="checked"');

        $T->set_block('product', 'ProdTypeRadio', 'ProdType');
        foreach ($LANG_SHOP['prod_types'] as $value=>$text) {
            if ($value == SHOP_PROD_COUPON && $_SHOP_CONF['gc_enabled'] == 0) {
                continue;
            }
            $T->set_var(array(
                'type_val'  => $value,
                'type_txt'  => $text,
                'type_sel'  => $this->prod_type == $value ? 'selected="selected"' : '',
            ));
            $T->parse('ProdType', 'ProdTypeRadio', true);
        }

        if (!self::isUsed($this->id)) {
            $T->set_var('candelete', 'true');
        }

        // If there are any images, retrieve and display the thumbnails.
        $T->set_block('product', 'PhotoRow', 'PRow');
        $i = 0;     // initialize $i in case there are no images
        foreach ($this->Images as $id=>$prow) {
            $T->set_var(array(
                'img_url'   => $this->ImageUrl($prow['filename'], 800, 600)['url'],
                'thumb_url' => $this->ImageUrl($prow['filename'])['url'],
                'seq_no'    => $i++,
                'img_id'    => $prow['img_id'],
            ) );
            $T->parse('PRow', 'PhotoRow', true);
        }

        $i = 0;
        foreach ($this->qty_discounts as $qty=>$amt) {
            $T->set_var(array(
                'disc_qty' . $i => $qty,
                'disc_amt' . $i => $amt,
            ) );
            $i++;
        }

        $Disc = Sales::getProduct($this->id);
        if (!empty($Disc)) {
            $DT = SHOP_getTemplate('sales_table', 'stable');
            $DT->set_var('edit_sale_url',
                SHOP_ADMIN_URL . '/index.php?sales');
            $DT->set_block('stable', 'SaleList', 'SL');
            foreach ($Disc as $D) {
                if ($D->discount_type == 'amount') {
                    $amount = Currency::getInstance()->Format($D->amount);
                } else {
                    $amount = $D->amount;
                }
                $DT->set_var(array(
                    'sale_start' => $D->start,
                    'sale_end'  => $D->end,
                    'sale_type' => $D->discount_type,
                    'sale_amt'  => $amount,
                ) );
                $DT->parse('SL', 'SaleList', true);
            }
            $DT->parse('output', 'stable');
            $T->set_var('sale_prices', $DT->finish($DT->get_var('output')));
        }

        $retval .= $T->parse('output', 'product');
        $retval .= COM_endBlock();
        return $retval;
    }   // function showForm()


    /**
     * Sets a boolean field to the opposite of the supplied value.
     *
     * @param   integer $oldvalue   Old (current) value
     * @param   string  $varname    Name of DB field to set
     * @param   integer $id         ID number of element to modify
     * @return  integer     New value, or old value upon failure
     */
    private static function _toggle($oldvalue, $varname, $id)
    {
        global $_TABLES;

        $id = (int)$id;

        // Determing the new value (opposite the old)
        $oldvalue = $oldvalue == 1 ? 1 : 0;
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES['shop.products']}
                SET $varname=$newvalue
                WHERE id=$id";
        //echo $sql;die;
        // Ignore SQL errors since varname is indeterminate
        DB_query($sql, 1);
        if (DB_error()) {
            SHOP_log("SQL error: $sql", SHOP_LOG_ERROR);
            return $oldvalue;
        } else {
            Cache::clear('products');
            Cache::clear('sitemap');
            return $newvalue;
        }
    }


    /**
     * Toggles the "enabled field.
     *
     * @uses    self::_toggle()
     * @param   integer $oldvalue   Original value
     * @param   integer $id         ID number of element to modify
     * @return  integer     New value, or old value upon failure
     */
    public static function toggleEnabled($oldvalue, $id)
    {
        return self::_toggle($oldvalue, 'enabled', $id);
    }


    /**
     * Toggles the "featured" field.
     *
     * @uses    self::_toggle()
     * @param   integer $oldvalue   Original value
     * @param   integer $id         ID number of element to modify
     * @return  integer     New value, or old value upon failure
     */
    public static function toggleFeatured($oldvalue, $id)
    {
        return self::_toggle($oldvalue, 'featured', $id);
    }



    /**
     * Determine if this product is mentioned in any purchase records.
     * Typically used to prevent deletion of product records that have
     * dependencies.
     * Can be called as Product::isUsed($item_id)
     *
     * @param   integer $item_id    ID of item to check
     * @return  boolean     True if used, False if not
     */
    public static function isUsed($item_id)
    {
        global $_TABLES;

        $item_id = (int)$item_id;
        if (DB_count($_TABLES['shop.orderitems'], 'product_id', $item_id) > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Display the detail page for the product.
     *
     * @param   integer $oi_id  OrderItem ID when linked from an order view
     * @return  string      HTML for the product page.
     */
    public function Detail($oi_id=0)
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER;

        USES_lib_comments();

        $prod_id = $this->id;
        if (!$this->canDisplay()) {
            return '';
        }

        // Get the currency object which is used repeatedly
        $Cur = Currency::getInstance();

        // Get the related OrderItem object, if any.
        // Used when displaying the product detail from an orde or cart view.
        // If none requested or the current user can't view the order, then
        // create an empty object for later use.
        if ($oi_id > 0) {
            $OI = new OrderItem($oi_id);
            if (!$OI->canView()) {
                $OI = new OrderItem;
            }
        } else {
            $OI = new OrderItem;
        }

        // Set the template dir based on the configured template version
        $T = SHOP_getTemplate(
            'product_detail_attrib', 'product',
            'detail/' . $_SHOP_CONF['product_tpl_ver']
        );
        $JT = new \Template(__DIR__ . '/../templates/detail');
        $JT->set_file('js', 'detail_js.thtml');

        $name = $this->name;
        $l_dscp = PLG_replaceTags($this->description);
        $s_dscp = PLG_replaceTags($this->short_description);

        // Highlight the query terms if coming from a search
        if (isset($_REQUEST['query']) && !empty($_REQUEST['query'])) {
            $name   = COM_highlightQuery($name, $_REQUEST['query']);
            $l_dscp = COM_highlightQuery($l_dscp, $_REQUEST['query']);
            $s_dscp = COM_highlightQuery($s_dscp, $_REQUEST['query']);
        }

        $this->_act_price = $this->getSalePrice();

        $qty_disc_txt = '';
        $T->set_block('product', 'qtyDiscTxt', 'disc');
        foreach ($this->qty_discounts as $qty=>$pct) {
            $T->set_var('qty_disc', sprintf($LANG_SHOP['buy_x_save'], $qty, $pct));
            $T->parse('disc', 'qtyDiscTxt', true);
        }

        // Get custom text input fields
        if ('' != $this->custom) {
            $T->set_block('product', 'CustAttrib', 'cAttr');
            $text_field_names = explode('|', $this->custom);
            foreach ($text_field_names as $id=>$text_field_name) {
                $val = $OI->getOptionByOG(0, $text_field_name)->oio_value;
                $T->set_var(array(
                    'fld_id'    => "cust_text_fld_$id",
                    'fld_name'  => htmlspecialchars($text_field_name),
                    'fld_val'   => htmlspecialchars($val),
                ) );
                $T->parse('cAttr', 'CustAttrib', true);
            }
        }

        // Retrieve the photos and put into the template
        $i = 0;
        foreach ($this->Images as $id=>$prow) {
            if (self::imageExists($prow['filename'])) {
                if ($i == 0) {
                    $T->set_var(array(
                        'main_img' => $this->ImageUrl($prow['filename'], 0, 0)['url'],
                        'main_imgfile' => $prow['filename'],
                    ) );
                }
                $T->set_block('product', 'Thumbnail', 'PBlock');
                $T->set_var(array(
                    'img_file'      => $prow['filename'],
                    'img_url'       => $this->ImageUrl($prow['filename'], 800, 600)['url'],
                    'thumb_url'     => $this->ImageUrl($prow['filename'])['url'],
                    'session_id'    => session_id(),
                ) );
                $T->parse('PBlock', 'Thumbnail', true);
                $i++;
            }
        }

        // Get the product options, if any, and set them into the form
        $cbrk = '';
        $init_price_adj = NULL;
        $json_opts = array();
        $this->_orig_price = $this->price;
        $T->set_block('product', 'AttrSelect', 'attrSel');
        if (is_array($this->options)) {
            foreach ($this->options as $id=>$Attr) {
                $type = 'select';
                $sel = $OI->getOptionByOG($Attr['og_id']);
                if ($sel !== false) {
                    $sel = $sel->attr_id;
                }
                if ($Attr['attr_name'] != $cbrk) {
                    // Adjust the price for cases where all attributes have prices
                    if ($init_price_adj !== NULL) {
                        $this->act_price += $init_price_adj;
                        $this->_orig_price += $init_price_adj;
                    }
                    $init_price_adj = NULL;
                    if ($cbrk != '') {      // end block if not the first element
                        $T->set_var(array(
                            'attr_name' => $cbrk,
                            'attr_options' => $attributes,
                            'opt_id' => $id,
                        ) );
                        $T->parse('attrSel', 'AttrSelect', true);
                    }
                    $cbrk = $Attr['attr_name'];
                    $attributes = '';
                }

                $json_opts[$id] = $Attr['attr_price'];
                if ($type == 'select') {
                    if ($init_price_adj === NULL) $init_price_adj = $Attr['attr_price'];
                    if ($Attr['attr_price'] != 0) {
                        $attr_str = sprintf(" ( %+0.2f )", $Attr['attr_price']);
                    } else {
                        $attr_str = '';
                    }
                    $val = htmlspecialchars($Attr['attr_value']);
                    /*$attributes .= '<option value="' . $id . '|' .
                        $val . '|' . $Attr['attr_price'] . '">' .
                        $val . $attr_str .
                        '</option>' . LB;*/
                    $selected = $id == $sel ? 'selected="selected"' : '';
                    $attributes .= '<option value="' . $id . '"' . $selected . '>' .
                        $val . $attr_str .
                        '</option>' . LB;
                /*} else {
                    $attributes .= "<input type=\"hidden\" name=\"on{$i}\"
                        value=\"{$Attr['attr_name']}\">\n";
                    $attributes .= $Attr['attr_name'] . ':</td>
                        <td><input class="uk-contrast uk-form" type"text" name="os' . $i. '" value="" size="32" /></td></tr>';
                */
                }
            }
            if ($cbrk != '') {      // finish off the last selection
                if ($init_price_adj !== NULL) {
                    $this->_act_price += $init_price_adj;
                    $this->_orig_price += $init_price_adj;
                }
                $T->set_var(array(
                    'attr_name' => $cbrk,
                    'attr_options' => $attributes,
                    'opt_id' => $id,
                ) );
                $T->parse('attrSel', 'AttrSelect', true);
            }
        }

        if ($this->getShipping()) {
            $shipping_txt = sprintf(
                $LANG_SHOP['plus_shipping'],
                $Cur->FormatValue($this->shipping_amt)
            );
        } else {
            $shipping_txt = '';
        }
        $T->set_var(array(
            'have_attributes'   => $this->hasAttributes(),
            'cur_code'          => $Cur->code,   // USD, etc.
            'id'                => $prod_id,
            'name'              => $name,
            'short_description' => $s_dscp,
            'description'       => $l_dscp,
            'cur_decimals'      => $Cur->Decimals(),
            'init_price'        => $Cur->FormatValue($this->_act_price),
            'price'             => $Cur->FormatValue($this->getPrice()),
            'orig_price'        => $Cur->FormatValue($this->_orig_price),
            'on_sale'           => $this->isOnSale(),
            'sale_name'         => $this->isOnSale() ? $this->getSale()->name : '',
            'img_cell_width'    => ($_SHOP_CONF['max_thumb_size'] + 20),
            'price_prefix'      => $Cur->Pre(),
            'price_postfix'     => $Cur->Post(),
            'onhand'            => $this->track_onhand ? $this->onhand : '',
            'qty_disc'          => count($this->qty_discounts),
            'session_id'        => session_id(),
            'shipping_txt'      => $shipping_txt,
            'stock_msg'         => $this->_OutOfStock(),
            'rating_bar'        => $this->ratingBar(),
        ) );
        $T->set_block('product', 'SpecialFields', 'SF');
        //var_dump($this->special_fields);die;
        foreach ($this->special_fields as $fld) {
            $T->set_var(array(
                'sf_name'   => $fld['name'],
                'sf_text'   => $fld['text'],
                'sf_class'  => isset($fld['class']) ? $fld['class'] : '',
                'sf_help'   => $fld['help'],
                'sf_type'   => isset($fld['type']) ? $fld['type'] : 'textarea',
            ) );
            $T->parse('SF', 'SpecialFields', true);
        }

        $buttons = $this->PurchaseLinks();
        $T->set_block('product', 'BtnBlock', 'Btn');
        foreach ($buttons as $name=>$html) {
            if ($name == 'add_cart') {
                // Set the add to cart button in the main form
                $T->set_var('add_cart_button', $html);
            } else {
                $T->set_var('buy_now_button', $html);
                $T->parse('Btn', 'BtnBlock', true);
            }
        }

        // Show the user comments if enabled globally and for this product
        if (plugin_commentsupport_shop() &&
                $this->comments_enabled != SHOP_COMMENTS_DISABLED) {
                // if enabled or closed
            if ($_CONF['commentsloginrequired'] == 1 && COM_isAnonUser()) {
                // Set mode to "disabled"
                $mode = -1;
            } else {
                $mode = $this->comments_enabled;
            }
            $T->set_var('usercomments',
                CMT_userComments($prod_id, $this->short_description, $_SHOP_CONF['pi_name'],
                    '', '', 0, 1, false, false, $mode));
        }

        if ($this->isAdmin) {
            // Add the quick-edit link for administrators
            $T->set_var(array(
                'pi_admin_url'  => SHOP_ADMIN_URL,
                'can_edit'      => 'true',
                'from_url'      => COM_getCurrentUrl(),
            ) );
        }
        $JT->set_var(array(
            'have_attributes'   => $T->get_var('have_attributes'),
            'price'             => $T->get_var('price'),
            'id'                => $T->get_var('id'),
            'cur_decimals'      => $T->get_var('cur_decimals'),
            'session_id'        => session_id(),
            'orig_price_val'    => $this->_orig_price,
            'opt_prices'        => json_encode($json_opts),
        ) );
        $JT->parse('output', 'js');
        $T->set_var('javascript', $JT->finish($JT->get_var('output')));

        // Update the hit counter
        DB_query("UPDATE {$_TABLES['shop.products']}
                SET views = views + 1
                WHERE id = '$prod_id'");

        $retval .= $T->parse('output', 'product');
        $retval = PLG_outputFilter($retval, 'shop');
        return $retval;
    }


    /**
     * Provide the file selector options for files already uploaded.
     *
     * @return  string      HTML for file selection dialog options
     */
    public function FileSelector()
    {
        global $_SHOP_CONF;

        $retval = '';

        $dh = opendir($_SHOP_CONF['download_path']);
        if ($dh) {
            while ($file = readdir($dh)) {
                if ($file == '.' || $file == '..')
                    continue;

                $sel = $file == $this->file ? 'selected="selected" ' : '';
                $retval .= "<option value=\"$file\" $sel>$file</option>\n";
            }
            closedir($dh);
        }

        return $retval;
    }


    /**
     * Get the array of error messages as an unumbered list.
     *
     * @return  string      Formatted error messages.
     */
    public function PrintErrors()
    {
        $retval = '';
        if (!empty($this->Errors)) {
            $retval .= '<ul>';
            foreach ($this->Errors as $msg) {
                $retval .= '<li>' . $msg . '</li>';
            }
            $retval .= '</ul>';
        }
        return $retval;
    }


    /**
     * Gets the purchase links appropriate for the product.
     * May be Shop buttons, login-required link, or download button.
     *
     * @param   string  $type   View type where the button will be shown
     * @return  array   Array of buttons as name=>html.
     */
    public function PurchaseLinks($type='detail')
    {
        global $_CONF, $_USER, $_SHOP_CONF, $_TABLES;

        $buttons = array();
        $this->_view = $type;

        // Indicate that an "add to cart" button should be returned along with
        // the "buy now" button.  If the product has already been purchased
        // and is available for immediate download, this will be turned off.
        $add_cart = $_SHOP_CONF['ena_cart'] == 1 ? true : false;

        if ($this->prod_type == SHOP_PROD_DOWNLOAD && $this->price == 0) {
            // Free, or unexpired downloads for non-anymous
            $T = SHOP_getTemplate('btn_download', 'download', 'buttons');
            $T->set_var('action_url', SHOP_URL . '/download.php');
            $T->set_var('id', $this->id);
            $buttons['download'] = $T->parse('', 'download');
            $add_cart = false;
        } elseif ($this->_OutOfStock() > 0) {
            // If out of stock, display but deny purchases
            $add_cart = false;
        } elseif ($_USER['uid'] == 1 && !$_SHOP_CONF['anon_buy'] &&
                !$this->hasAttributes() && $this->price > 0) {
            // Requires login before purchasing
            $T = SHOP_getTemplate('btn_login_req', 'login_req', 'buttons');
            $buttons['login'] = $T->parse('', 'login_req');
        } else {
            // Normal buttons for everyone else
            if ($this->canBuyNow() && $this->btn_type != '') {
                // Gateway buy-now buttons only used if no options
                foreach (Gateway::getAll() as $gw) {
                    if ($gw->Supports($this->btn_type)) {
                        $buttons[$gw->Name()] = $gw->ProductButton($this);
                    }
                }
            }
        }

        // All users and products get an add-to-cart button, if price > 0
        // and cart is enabled, and product is not a donation. Donations
        // can't be mixed with products, so don't allow adding to the cart.
        if ($add_cart && $this->btn_type != 'donation' &&
            ($this->price > 0 || !$this->canBuyNow()) ) {
            $T = new \Template(SHOP_PI_PATH . '/templates');
            $T->set_file(array(
                'cart'  => 'buttons/btn_add_cart_attrib.thtml',
            ) );
            $T->set_var(array(
                'item_name'     => htmlspecialchars($this->name),
                'item_number'   => $this->id,
                'short_description' => htmlspecialchars($this->short_description),
                'amount'        => $this->getPrice(),
                'action_url'    => SHOP_URL . '/index.php',
                //'form_url'  => $this->hasAttributes() ? '' : 'true',
                //'form_url'  => false,
                'form_url'  => $this->_view == 'list' ? true : false,
                'tpl_ver'   => $_SHOP_CONF['product_tpl_ver'],
                'frm_id'    => md5($this->id . rand()),
                'quantity'  => $this->getFixedQuantity(),
                'nonce'     => Cart::getInstance()->makeNonce($this->id . $this->name),
            ) );
            $buttons['add_cart'] = $T->parse('', 'cart');
        }
        return $buttons;
    }


    /**
     * Determine if this product has any attributes.
     *
     * @return  boolean     True if attributes exist, False if not.
     */
    public function hasAttributes()
    {
        return empty($this->options) ? false : true;
    }


    /**
     * Determine if this product has any quantity-based discounts.
     * Used to display "discounts available" message in the product liet.
     *
     * @return  boolean     True if attributes exist, False if not.
     */
    public function hasDiscounts()
    {
        // Have to assign to temp var to get empty() to work
        $discounts = $this->qty_discounts;
        return empty($discounts) ? false : true;
    }


    /**
     * Check if this product uses custom per-product text-input fields
     *
     * @return  boolean     True if custom fields are configured
     */
    public function hasCustomFields()
    {
        $cust = $this->custom;
        return empty($cust) ? false : true;
    }


    /**
     * Check if this product type uses special text-input fields.
     *
     * @return  boolean     True if special fields are configured
     */
    public function hasSpecialFields()
    {
        return empty($this->special_fields) ? false : true;
    }


    /**
     * Add a special field to a product.
     * The field will not be added if $fld_name already exists for the
     * product.
     * The prompt string may be supplied or, if blank, then $fld_name is used
     * to find a string in $LANG_SHOP. Final fallback is to use the field name
     * as the prompt.
     * Plugins should be sure to set $fld_lang.
     *
     * @param   string  $fld_name   Field Name
     * @param   string  $fld_lang   Field prompt, language string
     * @param   array   $opts       Array of option name=>value
     */
    public function addSpecialField($fld_name, $fld_lang = '', $opts=array())
    {
        global $LANG_SHOP, $LANG_SHOP_HELP;

        if (array_key_exists($fld_name, $this->special_fields)) {
            // Only add if the field doesn't already exist
            return;
        }

        if (empty($fld_lang)) {
            // No text supplied, try to get one from the language file.
            $fld_lang = SHOP_getVar($LANG_SHOP, $fld_name);
        }

        // Default to help string from the language file.
        // May be overridden from the $opts array if one is supplied there.
        $fld_help = SHOP_getVar($LANG_SHOP_HELP, $fld_name);

        $this->special_fields[$fld_name] = array(
            'name' => $fld_name,
            'text' => $fld_lang,
            'help' => $fld_help,
        );
        foreach ($opts as $opt_name=>$opt_data) {
            $this->special_fields[$fld_name][$opt_name] = $opt_data;
        }

        // If not provided in $opts, set the field type
        if (!array_key_exists('type', $this->special_fields[$fld_name])) {
            $this->special_fields[$fld_name]['type'] = 'text';
        }
    }


    /**
     * Determine if a "Buy Now" button is allowed for this item.
     * Items with attributes or a quantity discount schedule must be
     * purchased through the shopping cart to allow for proper price
     * calculation.
     *
     * @return  boolean     True to allow Buy Now, False to disable
     */
    public function canBuyNow()
    {
        if (
            !$this->canOrder()          // Can't be ordered, unavailable
            || $this->hasAttributes()   // no attributes to select
            || $this->hasDiscounts()    // no quantity-based discounts
            || $this->hasCustomFields() // no text fields to fill in
            || $this->hasSpecialFields()    // no special fields to fill in
        ) {
            // If any of the above apply, then the buy-now button can't be used.
            return false;
        }
        return true;
    }


    /**
     * Get the discount to apply based on the quantity of this item sold.
     *
     * @param   integer $quantity   Quantity of item sold
     * @return  float       Percentage discount to apply
     */
    public function getDiscount($quantity)
    {
        $retval = 0;

        if (is_array($this->qty_discounts)) {
            foreach ($this->qty_discounts as $qty=>$discount) {
                $qty = (int)$qty;
                if ($quantity < $qty) {     // haven't reached this discount level
                    break;
                } else {
                    $retval = (float)$discount;
                }
            }
        }
        return $retval;
    }



    /**
     * Get the unit price of this product, considering the specified options.
     * Quantity discounts are considered, the return value is the effictive
     * price per unit.
     *
     * @param   array   $options    Array of integer option values
     * @param   integer $quantity   Quantity, used to calculate discounts
     * @param   array   $override   Override elements (price, uid)
     * @return  float       Product price, including option
     */
    public function getPrice($options = array(), $quantity = 1, $override = array())
    {
        if (!is_array($options)) $options = explode(',', $options);
        if ($this->override_price && isset($override['price'])) {
            // If an override price is specified, just return it.
            return round((float)$override['price'], Currency::getInstance()->Decimals());
        } else {
            // Otherwise start with the effective sale price
            $price = $this->getSalePrice();
        }

        // Calculate the discount factor if a quantity discount is in play
        $discount_factor = (100 - $this->getDiscount($quantity)) / 100;

        // Add attribute prices to base price
        /*foreach ($options as $key) {
            $parts = explode('|', $key); // in case of "7|Black|1.50" option
            $key = $parts[0];
            if (isset($this->options[$key])) {
                $price += (float)$this->options[$key]['attr_price'];
            }
        }*/
        foreach ($options as $Opt) {
            if ($Opt->attr_id > 0) {
                $key = $Opt->attr_id;
                if (isset($this->options[$key])) {
                    $price += (float)$this->options[$key]['attr_price'];
                }
            }
        }

        // Discount the price, including attributes
        $price *= $discount_factor;
        $price = round($price, Currency::getInstance()->Decimals());
        return $price;
    }


    /**
     * Get the formatted price for display.
     * Used mainly to allow child classes to override the displayed price.
     *
     * @param   mixed   $price  Fixed price to use, NULL to use getPrice()
     * @return  string          Formatted price for display
     */
    public function getDisplayPrice($price = NULL)
    {
        if ($price === NULL) $price = $this->getPrice();
        return Currency::getInstance()->Format($price);
    }


    /**
     * Get the sales tax for this item based on the configured tax rate.
     *
     * @param   float   $price  Unit price
     * @param   integer $qty    Item quantity
     * @return  float           Sales tax ammount
     */
    public function getTax($price, $qty = 1)
    {
        if ($this->taxable) {
            return round(SHOP_getTaxRate() * $price * $qty, 2);
        } else {
            return 0;
        }
    }


    /**
     * Create and return a SKU for this product and the selected options.
     *
     * @param   object  $item   OrderItem object
     * @return  string      SKU string containing selected options.
     */
    public function getSKU($item)
    {
        $sku = array($this->name);

        // Get attributes selected from the available options
        // Use item_options since the class var doesn't work with empty()
        $item_options = $item->options;
        if (!empty($item_options)) {
            foreach ($item->options as $attr_id=>$OIO) {
                if (
                    array_key_exists($OIO->attr_id, $this->options) &&
                    !empty($this->options[$OIO->attr_id]['sku'])
                ) {
                    $sku[] = $this->options[$OIO->attr_id]['sku'];
                }
            }
        }
        return implode('-', $sku);
    }


    /**
     * Get the options display to be shown in the cart and on the order.
     * Returns a string like so:
     *      -- option1: option1_value
     *      -- option2: optoin2_value
     *
     * @param  object  $item   Specific OrderItem object from the cart
     * @return string      Option display
     */
    public function getOptionDisplay($item)
    {
        $retval = '';
        $opts = array();

        // Get attributes selected from the available options
        // Use item_options since the class var doesn't work with empty()
        $item_options = $item->options;
        if (!empty($item_options)) {
            $options = explode(',', $item_options);
            foreach ($options as $option) {
                $opts[] = array(
                    'opt_name'  => $this->options[$option]['attr_name'],
                    'opt_value' => $this->options[$option]['attr_value'],
                );
            }
        }

        // Get special fields submitted with the purchase
        if (is_array($item->extras)) {
            if (isset($item->extras['special']) && is_array($item->extras['special'])) {
                $sp_flds = $this->getSpecialFields($item->extras['special']);
                foreach ($sp_flds as $txt=>$val) {
                    $opts[] = array(
                        'opt_name'  => $txt,
                        'opt_value' => $val,
                    );
                }
            }
        }

        // Get text fields defined with the product
        $text_names = explode('|', $this->custom);
        if (
            !empty($text_names) &&
            isset($item->extras['custom']) &&
            is_array($item->extras['custom'])
        ) {
            foreach ($item->extras['custom'] as $tid=>$val) {
                if (array_key_exists($tid, $text_names) && !empty($val)) {
                    $opts[] = array(
                        'opt_name'  => $text_names[$tid],
                        'opt_value' => $val,
                    );
                }
            }
        }

        if (!empty($opts)) {
            $T = SHOP_getTemplate('view_options', 'options');
            $T->set_block('options', 'ItemOptions', 'ORow');
            foreach ($opts as $opt) {
                $T->set_var(array(
                    'opt_name'  => $opt['opt_name'],
                    'opt_value' => strip_tags($opt['opt_value']),
                ) );
                $T->parse('ORow', 'ItemOptions', true);
            }
            $retval .= $T->parse('output', 'options');
        }
        return $retval;
    }


    /**
     * Get the descriptive values for a specified set of options.
     *
     * @param   array   $options    Array of integer option values
     * @return  string      Comma-separate list of text values, or empty
     */
    public function getOptionDesc($options = array())
    {
        $opts = array();
        if (!is_array($options)) {
            $options = explode(',', $options);
        }
        foreach ($options as $key) {
            if (strpos($key, '|') !== false) {  // complete option strings
                list($key, $junk) = explode('|', $key);
            }
            if (isset($this->options[$key])) {
                $opts[] = $this->options[$key]['attr_value'];
            }
        }
        if (!empty($opts)) {
            $retval = implode(', ', $opts);
        } else {
            $retval = '';
        }
        return $retval;
    }


    /**
     * Handle the purchase of this item.
     *  - Update qty on hand if track_onhand is set (min. value 0)
     *
     * @param   object  $Item       Item record, to get options, etc.
     * @param   object  $Order      Optional order (not used yet)
     * @param   array   $ipn_data   IPN data (not used in this class)
     * @return  integer     Zero or error value
     */
    public function handlePurchase(&$Item, $Order=NULL, $ipn_data = array())
    {
        global $_TABLES;

        $status = 0;

        // update the qty on hand, if tracking and not already zero
        if ($this->track_onhand && $this->onhand > 0) {
            $sql = "UPDATE {$_TABLES['shop.products']} SET
                    onhand = GREATEST(0, onhand - {$Item->quantity})
                    WHERE id = '{$this->id}'";
            Cache::clear('products');
            Cache::clear('sitemap');
            DB_query($sql, 1);
            if (DB_error()) {
                SHOP_log("SQL errror: $sql", SHOP_LOG_ERROR);
                $status = 1;
            }
        }
        return $status;
    }


    /**
     * Handle a product refund.
     *
     * @param   object  $Order      Order object.
     * @param   array   $ipn_data   IPN data received
     */
    public function handleRefund($Order, $ipn_data = array())
    {
    }


    /**
     * Handle a "cancel purchase" message.
     *
     * @param   object  $Order      Order object.
     * @param   array   $ipn_data   IPN data received
     */
    public function cancelPurchase($Order, $ipn_data = array())
    {
    }


    /**
     * Get an option from the `options` property.
     *
     * @param   string  $key    Option name to retrieve
     * @return  mixed       Option value, False if not set
     */
    public function getOption($key)
    {
        if (isset($this->options[$key])) {
            return array(
                'name' => $this->options[$key]['attr_name'],
                'value' => $this->options[$key]['attr_value'],
                'price' => $this->options[$key]['attr_price'],
            );
        } else {
            return false;
        }
    }


    /**
     * Get the prompt for a custom field.
     * Returns "Undefined" if for some reason the field isn't defined.
     *
     * @param   integer $key    Array key into the $custom fields
     * @return  string      Custom field name, or "undefined"
     */
    public function getCustom($key=NULL)
    {
        static $custom = NULL;
        if ($custom === NULL) {
            $custom = explode('|', $this->custom);
        }
        if ($key === NULL) {
            return $custom;
        } elseif (isset($custom[$key])) {
            return $custom[$key];
        } else {
            return 'Undefined';
        }
    }


    /**
     * Duplicate this product.
     *  - Save the original product ID
     *  - Creates a new product record and get the new ID
     *  - Copies all images from oldid_x to newid_x
     *  - Creates records in the images table
     *
     * @return boolean     True on success, False on failure
     */
    public function Duplicate()
    {
        global $_TABLES, $_SHOP_CONF;

        if ($this->id == 0 || self::isPluginItem($this->id)) {
            // Don't handle new items or plugin products
            return false;
        }

        // Save the original ID, needed to copy image files
        $old_id = $this->id;

        // Set product variables to indicate a new product and save it.
        $this->isNew = true;
        $this->id = 0;
        $this->name = $this->name . ' - Copy';
        $this->Save();
        if ($this->id < 1) {
            SHOP_log("Error duplicating product id $old_id", SHOP_LOG_ERROR);
            return false;
        }
        $new_id = $this->id;

        // Copy all the image files
        foreach ($this->Images as $A) {
            $parts = explode('_', $A['filename']);
            $new_fname = "{$new_id}_{$parts[1]}";
            $src_f = $_SHOP_CONF['image_dir'] . '/' . $A['filename'];
            $dst_f = $_SHOP_CONF['image_dir'] . '/' . $new_fname;
            if (@copy($src_f, $dst_f)) {
                // copy successful, insert record into table
                $sql = "INSERT INTO {$_TABLES['shop.images']}
                            (product_id, filename)
                        VALUES ('$new_id', '" . DB_escapeString($new_fname) . "')";
                DB_query($sql);
            } else {
                SHOP_log("Error copying file $src_f to $dst_f, continuing", SHOP_LOG_ERROR);
            }
        }
        return true;
    }


    /**
     * Determine if this product is on sale.
     *
     * @return  boolean True if on sale, false if not
     */
    public function isOnSale()
    {
        $sp = $this->getSalePrice();
        return $this->price > 0 && $sp < $this->price ? true : false;
    }


    /**
     * Get the sale price for this item, if any.
     * First checks for an item-specific sale price and sale period,
     * then traverses up the category tree to find the first parent
     * category with an effective sale price.
     * Prices are cached for repeated calls.
     *
     * @see     self::isOnSale()
     * @return  float   Sale price, normal price if not on sale
     */
    public function getSalePrice()
    {
        return $this->getSale()->calcPrice($this->price);
    }


    /**
     * Sets and returns the private Sale object as the current effective sale.
     *
     * @return  object      Sale object
     */
    public function getSale()
    {
        if ($this->Sale === NULL) {
            $this->Sale = Sales::getEffective($this);
        }
        return $this->Sale;
    }


    /**
     * Check if an item can be ordered.
     * Uses canDisplay() to check access, availability dates, etc., then
     * rechecks stock status against the product's oversell setting.
     *
     * @uses    self::canDisplay()
     * @return  boolean     True if the product can be ordered.
     */
    public function canOrder()
    {
        if (
            $this->canDisplay() &&
            ($this->_isInStock() || $this->oversell == self::OVERSELL_ALLOW)
        ) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Check if this product can be displayed or purchased due to stock status.
     *
     * @param   integer $requested  Requested oversell value to sell item
     * @return  boolean     True if condition is met, False if not.
     */
    private function _isInStock()
    {
        // Not tracking stock, or have stock on hand, return true
        if ($this->track_onhand == 0 || $this->onhand > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Determine if a product can be displayed in the catalog.
     * Default availability dates are from 1900-01-01 to 9999-12-31.
     *
     * @param   boolean $isadmin    True if this is an admin, can view all
     * @return  boolean True if on sale, false if not
     */
    public function canDisplay($isadmin = false)
    {
        // If the product is disabled, return false now
        if ($this->id < 1 || !$this->enabled) {
            return false;
        }

        if ($isadmin) return true;  // Admin can always view and order

        // Check the user's permission, if not admin
        if (!$isadmin  && !$this->hasAccess()) {
            return false;
        }

        // If not in stock and oversell set to Hide, return false.
        if (!$this->_isInStock() && $this->oversell == self::OVERSELL_HIDE) {
            return false;
        }

        // Check that today is within the product's availability window
        $today = SHOP_now()->format('Y-m-d', true);
        if ($today < $this->avail_beg || $today > $this->avail_end) {
            return false;
        }

        // Finally, no conditions failed, return true
        return true;
    }


    /**
     * Check if tax should be charged on this item.
     * Checks both the product taxable flag and the configured tax rate.
     *
     * @return  boolean     True if taxable and there is a tax rate
     */
    public function isTaxable()
    {
        return $this->taxable && (SHOP_getTaxRate() > 0);
    }


    /**
     * Display the date, if present, or a blank field if effectively null.
     *
     * @param   string  $str    Date string, "0000-00-00" indicates empty
     * @return  string      Supplied date string, or "" if zeroes
     */
    private static function _InputDtFormat($str)
    {
        if ($str == '0000-00-00' || $str == self::MAX_DATE || $str == self::MIN_DATE)
            return '';
        else
            return $str;
    }


    /**
     * Determine if a given item number belongs to a plugin.
     * Looks for a colon in the item number, which will indicate a plugin
     * item number formated as "pi_name:item_number:other_opts"
     *
     * @param   mixed   $item_number    Item Number to check
     * @return  boolean     True if it's a plugin item, false if it's ours
     */
    public static function isPluginItem($item_number)
    {
        if (strpos($item_number, ':') > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Get the text string and value for special fields.
     * Used when displaying cart info
     *
     * @param   array   $values     Special field values
     * @return  array       Array of text=>value
     */
    public function getSpecialFields($values = array())
    {
        global $LANG_SHOP;

        $retval = array();
        if (!is_array($values)) {
            return $retval;
        }
        foreach ($this->special_fields as $fld_name=>$fld) {
            if (array_key_exists($fld_name, $values) && !empty($values[$fld_name])) {
                $retval[$fld['text']] = $values[$fld_name];
            }
        }
        return $retval;
    }


    /**
     * Helper function to create the cache key.
     *
     * @param   string  $id     Item ID
     * @param   string  $type   Optional item type
     * @return  string      Cache key
     */
    private static function _makeCacheKey($id, $type='')
    {
        if ($type != '') $type .= '_';
        return 'product_' . $type . $id;
    }


    /**
     * Determine if the current user has access to view this product.
     * Checks the related category for access.
     *
     * @return  boolean     True if access and purchase is allowed.
     */
    public function hasAccess()
    {
        // Make sure the category is set
        if (!$this->Cat) $this->Cat = Category::getInstance($this->cat_id);
        return $this->Cat->hasAccess();
    }


    /**
     * Get the product name. Allows for an override.
     *
     * @param   string  $override  Optional name override
     * @return  string              Product Name
     */
    public function getName($override = '')
    {
        return $override == '' ? $this->name : $override;
    }


    /**
     * Get the product short description. Allows for an override.
     *
     * @param   string  $override  Optional description override
     * @return  string              Product sort description
     */
    public function getDscp($override = '')
    {
        return $override == '' ? $this->short_description : $override;
    }


    /**
     * Get the URL to the item detail page.
     *
     * @param   integer $oi_id  Orde Item ID
     * @return  string      Item detail URL
     */
    public function getLink($oi_id=0)
    {
        global $_SHOP_CONF;

        $id = $_SHOP_CONF['use_sku'] ? $this->name : $this->id;
        $url = SHOP_URL . '/detail.php?id=' . $id;
        if ($oi_id > 0) {
            $url .= '&oi_id=' . (int)$oi_id;
        }
        return COM_buildUrl($url);
    }


    /**
     * Get additional text to add to the buyer's receipt for a product.
     *
     * @param   object  $orderitem  Line item to check.
     */
    public function EmailExtra($orderitem)
    {
        return '';
    }


    /**
     * Get the total shipping amount for this item based on quantity purchased/
     *
     * @param   integer $qty    Quantity purchased
     * @return  float           Total item fixed shipping charge
     */
    public function getShipping($qty = 1)
    {
        return $this->shipping_amt * (float)$qty;
    }


    /**
     * Get the total handling fee for this item based on quantity purchased
     *
     * @param   integer $qty    Quantity purchased
     * @return  float           Total handling charge
     */
    public function getHandling($qty = 1)
    {
        return (float)$this->handling * $qty;
    }


    /**
     * Get the fixed quantity that can be ordered per item view.
     * If this is zero, then an input box will be shown for the buyer to enter
     * a quantity. If nonzero, then the input box is a hidden variable with
     * the value set to the fixed quantity
     *
     * return   @integer    Fixed quantity number, zero for varible qty
     */
    public function getFixedQuantity()
    {
        return $this->_fixed_q;
    }


    /**
     * Determine if like items can be accumulated in the cart under a single
     * line item.
     * Normal products can be accumulated but some plugin products may not.
     *
     * @return  boolean     True if items can be accumulated, False if not
     */
    public function cartCanAccumulate()
    {
        return true;
    }


    /**
     * Check if this item is out of stock.
     *
     * @return  integer     Zero to behave normally, or 1 or 2 if out of stock.
     */
    private function _OutOfStock()
    {
        if ($this->track_onhand == 0 || $this->onhand > 0) {
            // Return zero, act normally.
            return 0;
        } else {
            // Return the oversell setting for the caller to act accordingly
            // when out of stock
            return $this->oversell;
        }
    }


    /**
     * Helper function to check if this item has a downloadable component.
     * Set $only to true to check if the item is only downloadable, e.g. no
     * virtual or physical component.
     *
     * @param   boolean $only   True to check if only download
     * @return  boolean     True if this is a physical item, False if not.
     */
    public function isDownload($only = false)
    {
        if ($only) {
            $retval = ($this->prod_type == SHOP_PROD_DOWNLOAD);
        } else {
            $retval = ($this->prod_type & SHOP_PROD_DOWNLOAD) == SHOP_PROD_DOWNLOAD;
        }
        return $retval;
    }


    /**
     * Helper function to check if this item has a physical component.
     *
     * @return  boolean     True if this is a physical item, False if not.
     */
    public function isPhysical()
    {
        return ($this->prod_type & SHOP_PROD_PHYSICAL) == SHOP_PROD_PHYSICAL;
    }


    /**
     * Get the images for this product.
     * Checks the cache first.
     * Also sets $this->Images
     *
     * @return  array   Array of images
     */
    public function getImages()
    {
        global $_TABLES;

        // If already loaded, just return the images.
        if ($this->Images !== NULL) {
            return $this->Images;
        }

        $cache_key = self::_makeCacheKey($this->id, 'img');
        $this->Images = Cache::get($cache_key);
        if ($this->Images === NULL) {
            $this->Images = array();
            $sql = "SELECT img_id, filename
                FROM {$_TABLES['shop.images']}
                WHERE product_id='". $this->id . "'";
            $res = DB_query($sql);
            while ($prow = DB_fetchArray($res, false)) {
                $this->Images[$prow['img_id']] = $prow;
            }
            Cache::set($cache_key, $this->Images, 'products');
        }
        return $this->Images;
    }


    /**
     * Get a single image to display in a block or product list.
     *
     * @return  string      Image filename, or empty string if there is none.
     */
    public function getOneImage()
    {
        if (is_array($this->Images)) {
            $img = reset($this->Images);
            return $img['filename'];
        } else {
            return '';
        }
    }


    /**
     * Update a product rating and perform related housekeeping tasks.
     *
     * @see     plugin_itemrated_shop()
     * @param   integer $id     Product ID
     * @param   integer $rating New rating value
     * @param   integer $votes  New total number of votes
     * @return  boolean     True on success, False on DB error
     */
    public static function updateRating($id, $rating, $votes)
    {
        global $_TABLES;

        $id = (int)$id;
        $rating = number_format($rating, 2, '.', '');
        $votes = (int)$votes;
        $sql = "UPDATE {$_TABLES['shop.products']} SET
            rating = $rating,
            votes = $votes
            WHERE id = $id";
        DB_query($sql);
        Cache::clear('products');
        return DB_error() ? false : true;
    }


    /**
     * Check if there are any products in the database.
     * Used to determine if data can be migrated from Paypal.
     *
     * @return  boolean     True if orders table is empty
     */
    public static function haveProducts()
    {
        global $_TABLES;

        return (
            DB_count($_TABLES['shop.products']) > 0
        );
    }


    /**
     * Get an array of all products.
     * Filtering is left to the caller.
     *
     * @return  array   Array of product objects
     */
    public static function getAll()
    {
        global $_TABLES;

        $cache_key = 'getall_products';
        $retval = Cache::get($cache_key);
        if ($retval === NULL) {
            $sql = "SELECT * FROM {$_TABLES['shop.products']}
                ORDER BY name ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $retval[$A['id']] = self::getInstance($A);
            }
            Cache::set($cache_key, $retval, 'products');
        }
        return $retval;
    }


    /**
     * Check if only one of this product may be added to the cart.
     * Buyers can normally buy any number of a product repeatedly.
     *
     * @return  boolean     True if product can be purchased only once
     */
    public function isUnique()
    {
        return false;
    }


    /**
     * Check if an image file exists on the filesystem.
     *
     * @param   string  $filename   Image filename, no path
     * @return  boolean     True if image file exists, False if not
     */
    public static function imageExists($filename)
    {
        global $_SHOP_CONF;

        return is_file($_SHOP_CONF['image_dir'] . DIRECTORY_SEPARATOR . $filename);
    }


    /**
     * Get the URL to a product image.
     *
     * @param   string  $filename   Image filename, no path
     * @param   integer $width      Optional width, assume thumbnail
     * @param   integer $height     Optional height, assume thumbnail
     * @return  array       Array of (url, width, height)
     */
    public function ImageUrl($filename = '', $width = 0, $height = 0)
    {
        global $_SHOP_CONF;

        // If no filename specified, get the first image name.
        if ($filename == '') {
            $filename = $this->getOneImage();
        }
        // If the filename is still empty, return nothing.
        if ($filename == '') {
            return array(
                'url'   => '',
                'width' => 0,
                'height' => 0,
            );;
        }

        $width = $width == 0 ? $_SHOP_CONF['max_thumb_size'] : (int)$width;
        $height = $height == 0 ? $_SHOP_CONF['max_thumb_size'] : (int)$height;
        $args = array(
            'filepath'  => $_SHOP_CONF['image_dir'] . DIRECTORY_SEPARATOR . $filename,
            'width'     => $width,
            'height'    => $height,
        );
        $status = LGLIB_invokeService('lglib', 'imageurl', $args, $output, $svc_msg);
        return $output;
    }


    /**
     * Check if this product supports product ratings.
     * Returns false if ratings are globaly disabled.
     *
     * @return  boolean     True if ratings are supported, False if not
     */
    public function supportsRatings()
    {
        global $_SHOP_CONF;

        return ($_SHOP_CONF['ena_ratings'] == 1) && $this->rating_enabled;
    }


    /**
     * Get the rating bar, if supported.
     *
     * @param   boolean $force_static   True to force static display.
     * @return  string      HTML for rating bar
     */
    public function ratingBar($force_static = false)
    {
        global $_USER;

        $ratedIds = RATING_getRatedIds($this->pi_name);

        if ($this->supportsRatings()) {
            if (in_array($this->id, $ratedIds)) {
                $static = 1;
                $voted = 1;
            } elseif (!$force_static && plugin_canuserrate_shop($this->id, $_USER['uid'])) {
                $static = 0;
                $voted = 0;
            } else {
                $static = 1;
                $voted = 0;
            }
            $retval = RATING_ratingBar(
                $this->pi_name,
                $this->id,
                $this->votes,
                $this->rating,
                $voted, 5, $static, 'sm'
            );
        } else {
            $retval = '';
        }
        return $retval;
    }


    /**
     * Get the cancel URL for buy-now buttons when the payment is cancelled.
     * Cart always uses cart.php.
     *
     * @return  string      URL to pass to the gateway for cancelling payment
     */
    public function getCancelUrl()
    {
        return ($this->cancel_url) ? $this->cancel_url : SHOP_URL . '/index.php';
    }


    /**
     * Product Admin List View.
     *
     * @param   integer $cat_id     Optional category ID to limit listing
     * @return  string      HTML for the product list.
     */
    public static function adminList($cat_id=0)
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $LANG_SHOP_HELP;

        $display = '';
        $sql = "SELECT
                p.id, p.name, p.short_description, p.description, p.price,
                p.prod_type, p.enabled, p.featured,
                p.avail_beg, p.avail_end, p.track_onhand, p.onhand, p.oversell,
                c.cat_id, c.cat_name
            FROM {$_TABLES['shop.products']} p
            LEFT JOIN {$_TABLES['shop.categories']} c
                ON p.cat_id = c.cat_id";

        $header_arr = array(
            array(
                'text'  => 'ID',
                'field' => 'id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_ADMIN['copy'],
                'field' => 'copy',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['enabled'],
                'field' => 'enabled',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['featured'],
                'field' => 'featured',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['product'],
                'field' => 'name',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['description'],
                'field' => 'short_description',
                'sort' => true,
            ),
            array(
                'text'  => $LANG_SHOP['category'],
                'field' => 'cat_name',
                'sort' => true,
            ),
            array(
                'text'  => $LANG_SHOP['price'],
                'field' => 'price',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['prod_type'],
                'field' => 'prod_type',
                'sort' => true,
            ),
            array(
                'text'  => $LANG_SHOP['status'],
                'field' => 'availability',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_ADMIN['delete'] .
                    '&nbsp;<i class="uk-icon uk-icon-question-circle tooltip" title="' .
                    $LANG_SHOP_HELP['hlp_prod_delete'] . '"></i>',
                'field' => 'delete', 'sort' => false,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'name',
            'direction' => 'asc',
        );

        $display .= COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );
        $display .= COM_createLink($LANG_SHOP['new_product'],
            SHOP_ADMIN_URL . '/index.php?editproduct=x',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );

        if ($cat_id > 0) {
            $def_filter = "WHERE c.cat_id='$cat_id'";
        } else {
            $def_filter = 'WHERE 1=1';
        }
        $query_arr = array(
            'table' => 'shop.products',
            'sql'   => $sql,
            'query_fields' => array(
                'p.name',
                'p.short_description',
                'p.description',
                'c.cat_name',
            ),
            'default_filter' => $def_filter,
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/index.php',
        );
        $cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
        $filter = $LANG_SHOP['category'] . ': <select name="cat_id"
            onchange="javascript: document.location.href=\'' .
                SHOP_ADMIN_URL .
                '/index.php?view=prodcts&amp;cat_id=\'+' .
                'this.options[this.selectedIndex].value">' .
            '<option value="0">' . $LANG_SHOP['all'] . '</option>' . LB .
            COM_optionList(
                $_TABLES['shop.categories'], 'cat_id, cat_name', $cat_id, 1
            ) .
            "</select>" . LB;

        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_productlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', '', ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the history screen.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;
        static $today = NULL;

        if ($today === NULL) {
            $today = SHOP_now()->format('Y-m-d');
        }
        $retval = '';

        switch($fieldname) {
        case 'copy':
            $retval .= COM_createLink(
                '<i class="uk-icon uk-icon-clone tooltip" title="' .
                $LANG_SHOP['copy_product'] . '"></i>',
                SHOP_ADMIN_URL . "/index.php?dup_product=x&amp;id={$A['id']}"
            );
            break;

        case 'edit':
            $retval .= COM_createLink(
                '<i class="uk-icon uk-icon-edit tooltip" title="' . $LANG_ADMIN['edit'] . '"></i>',
                SHOP_ADMIN_URL . "/index.php?editproduct=x&amp;id={$A['id']}"
            );
            break;

        case 'delete':
            if (!\Shop\Product::isUsed($A['id'])) {
                $retval .= COM_createLink(
                    '<i class="uk-icon uk-icon-trash uk-text-danger"></i>',
                    SHOP_ADMIN_URL. '/index.php?deleteproduct=x&amp;id=' . $A['id'],
                    array(
                        'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                        'title' => $LANG_SHOP['del_item'],
                        'class' => 'tooltip',
                    )
                );
            } else {
                $retval = '';
            }
            break;

        case 'enabled':
            if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                    id=\"togenabled{$A['id']}\"
                    onclick='SHOP_toggle(this,\"{$A['id']}\",\"enabled\",".
                    "\"product\");' />" . LB;
            break;

        case 'availability':
            $icon = 'uk-icon-circle';
            if ($A['avail_beg'] > $today || $A['avail_end'] < $today) {
                $cls = 'uk-text-danger';
                $caption = $LANG_SHOP['available'] . ' ' . $A['avail_beg'] . ' - ' . $A['avail_end'];
            } elseif ($A['track_onhand'] == 1 && $A['onhand'] < 1) {
                $cls = $A['oversell'] > 0 ? 'uk-text-danger' : 'uk-text-warning';
                $caption = $LANG_SHOP['out_of_stock'];
            } else {
                $cls = 'uk-text-success';
                $caption = $LANG_SHOP['available'] . '.';
                if ($A['track_onhand'] == 1) {
                    $caption .= "<br />{$LANG_SHOP['onhand']} = {$A['onhand']}.";
                }
            }
            $retval = "<i class=\"tooltip uk-icon $icon $cls\" title=\"$caption\"></i>";
            break;

        case 'featured':
            if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                id=\"togfeatured{$A['id']}\"
                onclick='SHOP_toggle(this,\"{$A['id']}\",\"featured\",".
                "\"product\");' />" . LB;
            break;

        case 'name':
            $retval = COM_createLink(
                $fieldvalue,
                SHOP_ADMIN_URL . '/report.php?run=itempurchase&item_id=' . $A['id'],
                array(
                    'class' => 'tooltip',
                    'title' => $LANG_SHOP['item_history'],
                )
             );
            break;

        case 'prod_type':
            if (isset($LANG_SHOP['prod_types'][$A['prod_type']])) {
                $retval = $LANG_SHOP['prod_types'][$A['prod_type']];
            } else {
                $retval = '';
            }
            break;

        case 'cat_name':
            $retval = COM_createLink(
                $fieldvalue,
                SHOP_ADMIN_URL . '/index.php?cat_id=' . $A['cat_id']
            );
            break;

        case 'short_description':
            $id = $_SHOP_CONF['use_sku'] ? $A['name'] : $A['id'];
            $retval = COM_createLink(
                $fieldvalue,
                SHOP_URL . '/detail.php?id=' . $id,
                array(
                    'class' => 'tooltip',
                    'title' => $LANG_SHOP['see_details'],
                )
            );
            break;

        case 'price':
            $retval = \Shop\Currency::getInstance()->FormatValue($fieldvalue);
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }


    /**
     * Verify that the product ID matches the specified value.
     * Used to ensure that the correct product was retrieved.
     *
     * @param   integer|string  $id     Expected product ID or name
     * @return  boolean     True if product matches.
     */
    public function verifyID($id)
    {
        global $_SHOP_CONF;

        if ($_SHOP_CONF['use_sku']) {
            return $this->name == $id;
        } else {
            return $this->id == $id;
        }
    }


    /**
     * Validate the form fields and return an array of errors.
     *
     * return   array   Array of error messages, empty if all is valid
     */
    private function _Validate()
    {
        global $_TABLES, $LANG_SHOP;

        $errors = array();
        $sku = DB_escapeString($this->name);
        $sku_err = (int)DB_getItem(
            $_TABLES['shop.products'],
            'count(*)',
            "name = '$sku' AND id <> {$this->id}"
        );

        if ($sku_err > 0) {
            $errors[] = $LANG_SHOP['err_dup_sku'];
        }
        return $errors;
    }


    /**
     * Get the ID of the first product in the database.
     * Used to set the first item in selection lists.
     * Relies on the primary key on the `id` field.
     *
     * @return  integer     Product ID
     */
    public static function getFirst()
    {
        global $_TABLES;

        return (int)DB_getItem($_TABLES['shop.products'], 'id');
    }

}

?>
