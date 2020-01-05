<?php
/**
 * Class to handle addresses for suppliers and brands.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Class for supplier and brand information
 * @package shop
 */
class Supplier extends Address
{
    /** Flag indicates that this is a supplier (default)
     * @var integer */
    private $is_supplier = 1;

    /** Flag indicates that this is a product brand (not default)
     * @var integer */
    private $is_brand = 0;


    /**
     * Constructor. Reads in the specified record,
     *
     * @param   integer     $sup_id    Supplier/Brand ID
     */
    public function __construct($sup_id=0)
    {
        global $_TABLES, $_SHOP_CONF;

        $this->setID($sup_id);
        $this->setCountry($_SHOP_CONF['country']);
        $this->setState($_SHOP_CONF['state']);
        if ($this->getID() > 0) {
            $res = DB_query(
                "SELECT * FROM {$_TABLES['shop.suppliers']}
                WHERE sup_id = {$this->getID()}"
            );
            if (DB_numRows($res) == 1) {
                $A = DB_fetchArray($res, false);
                $this->setAddress($A);
            } else {
                $this->setID(0);
            }
        }
    }


    /**
     * Get a specific supplier by ID.
     *
     * @param   integer $addr_id    Address ID to retrieve
     * @return  object      Address object, empty if not found
     */
    public static function getInstance($addr_id)
    {
        static $addrs = array();

        $addr_id = (int)$addr_id;
        if (isset($addrs[$addr_id])) {
            return $addrs[$addr_id];
        } else {
            return new self($addr_id);
        }
    }


    /**
     * Set the record values into local variables.
     *
     * @param   array   $data   Form or DB record data
     * @return  object  $this
     */
    private function setAddress($data)
    {
        return $this->setID(SHOP_getVar($data, 'sup_id', 'integer'))
            ->setName(SHOP_getVar($data, 'name'))
            ->setCompany(SHOP_getVar($data, 'company'))
            ->setAddress1(SHOP_getVar($data, 'address1'))
            ->setAddress2(SHOP_getVar($data, 'address2'))
            ->setCity(SHOP_getVar($data, 'city'))
            ->setState(SHOP_getVar($data, 'state'))
            ->setPostal(SHOP_getVar($data, 'zip'))
            ->setCountry(SHOP_getVar($data, 'country', 'string', $_SHOP_CONF['country']))
            ->setPhone(SHOP_getVar($data, 'phone', 'string'))
            ->setIsSupplier(SHOP_getVar($data, 'is_supplier', 'integer', 1))
            ->setIsBrand(SHOP_getVar($data, 'is_brand', 'integer', 0));
    }


    /**
     * Set the `supplier` flag for this supplier.
     * Public so it can be called from the upgrade process.
     *
     * @param   integer $val    Zero or one
     * @return  object  $this
     */
    public function setIsSupplier($val)
    {
        $this->is_supplier = $val == 0 ? 0 : 1;
        return $this;
    }


    /**
     * Get the is_supplier flag value.
     *
     * @return  integer     Value of is_supplier flag
     */
    private function getIsSupplier()
    {
        return (int)$this->is_supplier;
    }


    /**
     * Set the `brand` flag for this supplier.
     * Public so it can be called from the upgrade process.
     *
     * @param   integer $val    Zero or one
     * @return  object  $this
     */
    public function setIsBrand($val)
    {
        $this->is_brand = $val == 0 ? 0 : 1;
        return $this;
    }


    /**
     * Get the is_brand flag value.
     *
     * @return  integer     Value of is_brand flag
     */
    private function getIsBrand()
    {
        return (int)$this->is_brand;
    }


    /**
     * Get the display name.
     * If the company is provided, return it. Otherwise return the name.
     * The company name should always be available.
     *
     * @return  string  Company or Individual name.
     */
    public function getDisplayName()
    {
        return empty($this->getCompany()) ? $this->getName() : $this->getCompany();
    }


    /**
     * Get a selection list for brand, supplier, or all records.
     *
     * @param   integer $sel    Selected record ID
     * @param   string  $type   Type (brand or supplier), empty for all
     * @return  string      HTML `<option>` elements
     */
    public static function getSelection($sel=0, $type='')
    {
        global $_TABLES;

        switch ($type) {
        case 'brand':
        case 'supplier':
            $where = "is_{$type} = 1";
            break;
        default:
            $where = '';
            break;
        }
        return COM_optionList(
            $_TABLES['shop.suppliers'],
            'sup_id,company',
            (int)$sel,
            1,
            $where
        );
    }


    /**
     * Get the selection list for the brand.
     *
     * @uses    self::getSelection()
     * @param   integer $sel    Selected record ID
     * @return  string      HTML `<option>` elements
     */
    public static function getBrandSelection($sel=0)
    {
        return self::getSelection($sel, 'brand');
    }


    /**
     * Get the selection list for the supplier.
     *
     * @uses    self::getSelection()
     * @param   integer $sel    Selected record ID
     * @return  string      HTML `<option>` elements
     */
    public static function getSupplierSelection($sel=0)
    {
        return self::getSelection($sel, 'supplier');
    }


    /**
     * Save the supplier information.
     *
     * @param   array   $A  Optional data array from $_POST
     * @return  boolean     True on success, False on failure
     */
    public function Save($A=NULL)
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->setAddress($A);
        }

        if ($this->getID() > 0) {
            $sql1 = "UPDATE {$_TABLES['shop.suppliers']} SET ";
            $sql3 = " WHERE sup_id='" . $this->getID() . "'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['shop.suppliers']} SET ";
            $sql3 = '';
        }
        $sql2 = "name = '" . DB_escapeString($this->getName()) . "',
                company = '" . DB_escapeString($this->getCompany()) . "',
                address1 = '" . DB_escapeString($this->getAddress1()) . "',
                address2 = '" . DB_escapeString($this->getAddress2()) . "',
                city = '" . DB_escapeString($this->getCity()) . "',
                state = '" . DB_escapeString($this->getState()) . "',
                country = '" . DB_escapeString($this->getCountry()) . "',
                phone = '" . DB_escapeString($this->getPhone()) . "',
                zip = '" . DB_escapeString($this->getPostal()) . "',
                is_supplier = {$this->getIsSupplier()},
                is_brand = {$this->getIsBrand()}";
        $sql = $sql1 . $sql2 . $sql3;
        //var_dump($this);die;
        //echo $sql;die;
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        if (!DB_error()) {
            if ($this->getID() == 0) {
                $this->setID(DB_insertID());
            }
            return true;
        } else {
            return false;
        }
    }


    /**
     * Delete all information for a supplier.
     *
     * @param   integer $sup_id     Supplier ID
     */
    public static function deleteSupplier($sup_id)
    {
        global $_TABLES;

        $sup_id = (int)$sup_id;
        DB_delete($_TABLES['shop.suppliers'], 'sup_id', $sup_id);
    }


    /**
     * Creates the address edit form.
     * Pre-fills values from another address if supplied
     *
     * @param   string  $type   Address type (billing or shipping)
     * @param   array   $A      Optional values to pre-fill form
     * @param   integer $step   Current step number
     * @return  string          HTML for edit form
     */
    public function Edit()
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP;

        $T = new \Template(SHOP_PI_PATH . '/templates');
        $T->set_file('form', 'supplier_form.thtml');
        $T->set_var(array(
            'entry_id'  => $this->getID(),
            'name'      => $this->getName(),
            'company'   => $this->getCompany(),
            'address1'  => $this->getAddress1(),
            'address2'  => $this->getAddress2(),
            'city'      => $this->getCity(),
            'state'     => $this->getState(),
            'zip'       => $this->getPostal(),
            'country'   => $this->getCountry(),
            'phone'     => $this->getPhone(),
            'brand_chk' => $this->getIsBrand() ? 'checked="checked"' : '',
            'supplier_chk' => $this->getIsSupplier() ? 'checked="checked"' : '',
            'doc_url'   => SHOP_getDocURL('supplier_form'),
        ) );
        $T->parse('output','form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Sets a boolean field to the opposite of the supplied value.
     *
     * @param   integer $oldvalue   Old (current) value
     * @param   string  $varname    Name of DB field to set
     * @param   integer $id         ID number of element to modify
     * @return  integer     New value, or old value upon failure
     */
    public static function Toggle($oldvalue, $varname, $id)
    {
        global $_TABLES;

        $id = (int)$id;
        switch ($varname) {     // allow only valid field names
        case 'is_supplier':
        case 'is_brand':
            // Determing the new value (opposite the old)
            $oldvalue = $oldvalue == 1 ? 1 : 0;
            $newvalue = $oldvalue == 1 ? 0 : 1;

            $sql = "UPDATE {$_TABLES['shop.suppliers']}
                SET $varname=$newvalue
                WHERE sup_id=$id";
            // Ignore SQL errors since varname is indeterminate
            DB_query($sql, 1);
            if (DB_error()) {
                SHOP_log("SQL error: $sql", SHOP_LOG_ERROR);
                return $oldvalue;
            } else {
                return $newvalue;
            }
        }
    }


    /**
     * Supplier/Brand admin list view.
     *
     * @return  string      HTML for the list.
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN;

        $header_arr = array(
            array(
                'text' => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['name'],
                'field' => 'name',
                'sort' => false,
            ),
            array(
                'text' => $LANG_SHOP['company'],
                'field' => 'company',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['city'],
                'field' => 'city',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['state'],
                'field' => 'state',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['country'],
                'field' => 'country',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['supplier'],
                'field' => 'is_supplier',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['brand'],
                'field' => 'is_brand',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'company',
            'direction' => 'ASC',
        );

        $display = COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $query_arr = array(
            'table' => 'shop.suppliers',
            'sql' => "SELECT * FROM {$_TABLES['shop.suppliers']}",
            'query_fields' => array(),
            'default_filter' => '',
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/index.php?suppliers',
        );

        $display .= '<div>' . COM_createLink($LANG_SHOP['new_supplier'],
            SHOP_ADMIN_URL . '/index.php?edit_sup=0',
            array('class' => 'uk-button uk-button-success')
        ) . '</div>';
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_supplierlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the Suppliers admin list.
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

        $retval = '';
        switch($fieldname) {
        case 'edit':
            $retval = COM_createLink(
                Icon::getHTML('edit'),
                SHOP_ADMIN_URL . '/index.php?edit_sup&id=' . $A['sup_id']
            );
            break;

        case 'delete':
            $retval = COM_createLink(
                Icon::getHTML('delete'),
                SHOP_ADMIN_URL . '/index.php?del_sup&id=' . $A['sup_id'],
                array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                )
            );
            break;

        case 'is_supplier':
        case 'is_brand':
            $chk = $fieldvalue == 1 ? 'checked="checked"' : '';
            $retval .= "<input type=\"checkbox\" $chk value=\"1\" name=\"{$fieldname}_check\"
                    id=\"tog{$fieldname}{$A['sup_id']}\"
                    onclick='SHOP_toggle(this,\"{$A['sup_id']}\",\"$fieldname\",".
                    "\"supplier\");' />" . LB;
//            $retval .= '<input type="checkbox" id="' . $fieldname . '_' . $A['sup_id'] . '" ' . $chk . '/>';
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }

}   // class Supplier 

?>