<?php
/**
 * Plugin-specific functions for the Shop plugin for glFusion.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2019 Lee Garner
 * @package     shop
 * @version     v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Class to display the product catalog.
 * @package shop
 */
class Catalog
{
    /**
     * Diaplay the product catalog items.
     *
     * @param   integer $cat_id     Optional category ID to limit display
     * @return  string      HTML for product catalog.
     */
    public static function Render($cat_id = 0)
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_USER, $_PLUGINS;

        $isAdmin = plugin_ismoderator_shop() ? true : false;
        $cat_name = '';
        $cat_img_url = '';
        $display = '';
        $cat_sql = '';
        $Cat = Category::getInstance($cat_id);
        $Cart = Cart::getInstance();

        // If a cat ID is requested but doesn't exist or the user can't access
        // it, redirect to the homepage.
        if ($Cat->cat_id > 0 && ($Cat->isNew || !$Cat->hasAccess())) {
            echo COM_refresh(SHOP_URL);
            exit;
        }

        // Get the root category and see if the requested category is root.
        $RootCat = Category::getRoot();
        $cat_name = $Cat->cat_name;
        $cat_desc = $Cat->description;
        $cat_img_url = $Cat->ImageUrl();
        if ($Cat->parent_id > 0) {
            // Get the sql to limit by category
            $tmp = Category::getTree($Cat->cat_id);
            $cats = array();
            foreach ($tmp as $xcat_id=>$info) {
                $cats[] = $xcat_id;
            }
            if (!empty($cats)) {
                $cat_sql = implode(',', $cats);
                $cat_sql = " AND c.cat_id IN ($cat_sql)";
            }
        }

        // Display top-level categories
        $A = array(
            $RootCat->cat_id => array(
                'name' => $RootCat->cat_name,
            ),
        );
        $tmp = $RootCat->getChildren();
        foreach ($tmp as $tmp_cat_id=>$C) {
            if ($C->parent_id == $RootCat->cat_id && $C->hasAccess()) {
                $A[$C->cat_id] = array(
                    'name' => $C->cat_name,
                    'count' => $C->cnt,
                );
            }
        }

        // Now get categories from plugins
        /*foreach ($_PLUGINS as $pi_name) {
            $pi_cats = PLG_callFunctionForOnePlugin('plugin_shop_getcategories_' . $pi_name);
            if (is_array($pi_cats) && !empty($pi_cats)) {
                foreach ($pi_cats as $data) {
                    $A[] = $data;
                }
            }
        }*/

        $CT = new \Template(__DIR__ . '/templates');
        $CT->set_file('catlinks', 'category_links.thtml');
        if ($cat_img_url != '') {
            $CT->set_var('catimg_url', $cat_img_url);
        }

        $CT->set_block('catlinks', 'CatLinks', 'link');
        foreach ($A as $category => $info) {
            if (isset($info['url'])) {
                $url = $info['url'];
            } elseif ($category == $RootCat->cat_id) {
                $url = SHOP_URL;
            } else {
                $url = SHOP_URL . '/index.php?category=' . urlencode($category);
            }
            $CT->set_var(array(
                'category_name' => $info['name'],
                'category_link' => $url,
            ) );
            $CT->parse('link', 'CatLinks', true);
        }
        $display .= $CT->parse('', 'catlinks');

        /*
         * Create the product sort selector
         */
        if (isset($_REQUEST['sortby'])) {
            $sortby = $_REQUEST['sortby'];
        } else {
            $sortby = SHOP_getVar($_SHOP_CONF, 'order', 'string', 'name');
        }
        switch ($sortby){
        case 'price_l2h':   // price, low to high
            $sql_sortby = 'price ASC';
            break;
        case 'price_h2l':   // price, high to low
            $sql_sortby = 'price DESC';
            break;
        case 'top_rated':
            $sql_sortby = 'rating DESC, votes DESC';
            break;
        case 'newest':
            $sql_sortby = 'dt_add DESC';
            break;
        case 'name':
        default:
            $sortby = 'name';
            $sql_sortby = 'short_description ASC';
            break;
        }
        $sortby_options = '';
        foreach ($LANG_SHOP['list_sort_options'] as $value=>$text) {
            $sel = $value == $sortby ? ' selected="selected"' : '';
            $sortby_options .= "<option value=\"$value\" $sel>$text</option>\n";
        }

        // Get products from database. "c.enabled is null" is to allow products
        // with no category defined
        $today = $_CONF['_now']->format('Y-m-d', true);
        $sql = " FROM {$_TABLES['shop.products']} p
                LEFT JOIN {$_TABLES['shop.categories']} c
                    ON p.cat_id = c.cat_id
                WHERE p.enabled=1
                AND p.avail_beg <= '$today' AND p.avail_end >= '$today'
                AND (
                    (c.enabled=1 " . SEC_buildAccessSql('AND', 'c.grp_access') . ")
                    OR c.enabled IS NULL
                    )
                AND (
                    p.track_onhand = 0 OR p.onhand > 0 OR p.oversell < 2
                    ) $cat_sql";

        $search = '';
        // Add search query, if any
        if (
            isset($_REQUEST['query']) &&
            !empty($_REQUEST['query']) &&
            !isset($_REQUEST['clearsearch'])
        ) {
            $query_str = urlencode($_REQUEST['query']);
            $search = DB_escapeString($_REQUEST['query']);
            $fields = array('p.name', 'c.cat_name', 'p.short_description', 'p.description',
                    'p.keywords');
            $srches = array();
            foreach ($fields as $fname) {
                $srches[] = "$fname like '%$search%'";
            }
            $srch = ' AND (' . implode(' OR ', $srches) . ')';
            $sql .= $srch;
        }
        $pagenav_args = array();

        // If applicable, order by
        $sql .= " ORDER BY $sql_sortby";
        $sql_key = md5($sql);
        //echo $sql;die;

        // Count products from database
        $cache_key = Cache::makeKey('prod_cnt_' . $sql_key);
        $count = Cache::get($cache_key);
        if ($count === NULL) {
            $res = DB_query('SELECT COUNT(*) as cnt ' . $sql);
            $x = DB_fetchArray($res, false);
            $count = SHOP_getVar($x, 'cnt', 'integer');
            Cache::set($cache_key, $count, array('products', 'categories'));
        }

        // If applicable, handle pagination of query
        $prod_per_page = SHOP_getVar($_SHOP_CONF, 'prod_per_page', 'integer', 20);
        if ($prod_per_page > 0) {
            // Make sure page requested is reasonable, if not, fix it
            if (!isset($_REQUEST['page']) || $_REQUEST['page'] <= 0) {
                $_REQUEST['page'] = 1;
            }
            $page = (int)$_REQUEST['page'];
            $start_limit = ($page - 1) * $prod_per_page;
            if ($start_limit > $count) {
                $page = ceil($count / $prod_per_page);
            }
            // Add limit for pagination (if applicable)
            if ($count > $prod_per_page) {
                $sql .= " LIMIT $start_limit, $prod_per_page";
            }
        }

        // Re-execute query with the limit clause in place
        $sql_key = md5($sql);
        $cache_key = Cache::makeKey('prod_list_' . $sql_key);
        $Products = Cache::get($cache_key);
        if ($Products === NULL) {
            $res = DB_query('SELECT p.* ' . $sql);
            $Products = array();
            while ($A = DB_fetchArray($res, false)) {
                $Products[] = Product::getInstance($A);
            }
            Cache::set($cache_key, $Products, array('products', 'categories'));
        }

        // Create product template
        if (empty($_SHOP_CONF['list_tpl_ver'])) $_SHOP_CONF['list_tpl_ver'] = 'v1';
        $T = SHOP_getTemplate(array(
            'wrapper'   => 'list/' . $_SHOP_CONF['list_tpl_ver'] . '/wrapper',
            'start'   => 'product_list_start',
            'end'     => 'product_list_end',
            'download'  => 'buttons/btn_download',
            'login_req' => 'buttons/btn_login_req',
            'btn_details' => 'buttons/btn_details',
        ) );

        $T->set_var(array(
            'pi_url'        => SHOP_URL,
            //'user_id'       => $_USER['uid'],
            'currency'      => $_SHOP_CONF['currency'],
            'breadcrumbs'   => $cat_id > 0 ? $Cat->Breadcrumbs() : '',
            'search_text'   => $search,
            'tpl_ver'       => $_SHOP_CONF['list_tpl_ver'],
            'sortby_options' => $sortby_options,
            'sortby'        => $sortby,
            'table_columns' => $_SHOP_CONF['catalog_columns'],
            'cat_id'        => $cat_id == 0 ? '' : $cat_id,
            'query'         => $query_str,
        ) );

        if (!empty($cat_name)) {
            $T->set_var(array(
                'title'     => $cat_name,
                'cat_desc'  => $cat_desc,
                'cat_img_url' => $cat_img_url,
            ) );
        } else {
            $T->set_var('title', $LANG_SHOP['blocktitle']);
        }
        $T->set_var('have_sortby', true);

        $display .= $T->parse('', 'start');

        if ($_SHOP_CONF['ena_ratings'] == 1) {
            $SHOP_ratedIds = SHOP_getRatedIds($_SHOP_CONF['pi_name']);
        }

        // Display each product
        $prodrows = 0;
        $T->set_block('wrapper', 'ProductItems', 'PI');
        foreach ($Products as $P) {
            // Don't display products if the viewer doesn't have access
            if (!$P->canDisplay()) {
                continue;
            }

            $prodrows++;
            $pic_filename = $P->getOneImage();
            $T->set_var(array(
                'item_id'       => $P->id,
                'name'          => htmlspecialchars($P->getName()),
                'short_description' => htmlspecialchars(PLG_replacetags($P->short_description)),
                'img_cell_width' => ($_SHOP_CONF['max_thumb_size'] + 20),
                'encrypted'     => '',
                'item_url'      => COM_buildUrl(SHOP_URL . '/detail.php?id='. $P->id),
                'img_cell_width' => ($_SHOP_CONF['max_thumb_size'] + 20),
                'track_onhand'  => $P->track_onhand ? 'true' : '',
                'qty_onhand'    => $P->onhand,
                'has_discounts' => $P->hasDiscounts() ? 'true' : '',
                'price'         => $P->getDisplayPrice(),
                'orig_price'    => $P->getDisplayPrice($P->price),
                'on_sale'       => $P->isOnSale(),
                'small_pic'     => $P->ImageUrl()['url'],
                'onhand'        => $P->track_onhand ? $P->onhand : '',
                'tpl_ver'       => $_SHOP_CONF['list_tpl_ver'],
                'nonce'         => $Cart->makeNonce($P->id . $P->getName()),
                'can_add_cart'  => $P->canBuyNow(),
                'rating_bar'    => $P->ratingBar(true),
            ) );

            if ($isAdmin) {
                $T->set_var(array(
                    'is_admin'  => 'true',
                    'pi_admin_url' => SHOP_ADMIN_URL,
                ) );
            }

            // Get the product buttons for the list
            $T->set_block('product', 'BtnBlock', 'Btn');
            if (!$P->hasAttributes() && !$P->hasCustomFields() && !$P->hasSpecialFields()) {
                // Buttons only show in the list if there are no options to select
                $buttons = $P->PurchaseLinks('list');
                foreach ($buttons as $name=>$html) {
                    $T->set_var('button', $html);
                    $T->parse('Btn', 'BtnBlock', true);
                }
            } else {
                if ($_SHOP_CONF['ena_cart']) {
                    // If the product has attributes, then the cart must be
                    // enabled to allow purchasing
                    $button = $T->parse('', 'btn_details') . '&nbsp;';
                    $T->set_var('button', $button);
                    $T->parse('Btn', 'BtnBlock', true);
                }
            }

            $T->parse('PI', 'ProductItems', true);
            $T->clear_var('Btn');
        }

        // Get products from plugins.
        // For now, this hack shows plugins only on the first page, since
        // they're not included in the page calculation.
        if (
            $_SHOP_CONF['show_plugins']&&
            $page == 1 &&
            ( $cat_id == 0 || $cat_id == $RootCat->cat_id ) &&
            empty($search)
        ) {
            // Get the currency class for formatting prices
            $Cur = Currency::getInstance();
            foreach ($_PLUGINS as $pi_name) {
                $status = LGLIB_invokeService(
                    $pi_name,
                    'getproducts',
                    array(),
                    $plugin_data,
                    $svc_msg
                );
                if ($status != PLG_RET_OK || empty($plugin_data)) continue;

                foreach ($plugin_data as $A) {
                    // Reset button values
                    $buttons = '';
                    if (!isset($A['buttons'])) $A['buttons'] = array();

                    // If the plugin has a getDetailPage service function, use it
                    // to wrap the item's detail page in the catalog page.
                    // Otherwise just use a link to the product's url.
                    if (isset($A['have_detail_svc'])) {
                        $item_url = SHOP_URL . '/index.php?pidetail=' . $A['id'];
                    } elseif (isset($A['url'])) {
                        $item_url = $A['url'];
                    } else {
                        $item_url = '';
                    }
                    $P = \Shop\Product::getInstance($A['id']);
                    $price = $P->getPrice();
                    $T->set_var(array(
                        'id'        => $P->id,          // required
                        'item_id'   => $P->item_id,     // required
                        'name'      => $P->short_description,
                        'short_description' => $P->short_description,
                        'encrypted' => '',
                        'item_url'  => $item_url,
                        'track_onhand' => '',   // not available for plugins
                        'small_pic' => $P->ImageUrl()['url'],
                        'on_sale'   => '',
                        'nonce'     => $Cart->makeNonce($A['id'] . $item_dscp),
                        'can_add_cart'  => true,
                        'rating_bar' => $P->ratingBar(true),
                    ) );
                    if ($price > 0) {
                        $T->set_var('price', $Cur->Format($price));
                    } else {
                        $T->clear_var('price');
                    }

                    if ($price > 0 && $_USER['uid'] == 1 && !$_SHOP_CONF['anon_buy']) {
                        $buttons .= $T->set_var('', 'login_req') . '&nbsp;';
                    /*} elseif (
                        (!isset($A['prod_type']) || $A['prod_type'] > SHOP_PROD_PHYSICAL) &&
                        $A['price'] == 0
                    ) {
                        // Free items or items purchased and not expired, allow download.
                        $buttons .= $T->set_var('', 'download') . '&nbsp;';*/
                    } elseif (is_array($A['buttons'])) {
                        // Buttons for everyone else
                        $T->set_block('wrapper', 'BtnBlock', 'Btn');
                        foreach ($A['buttons'] as $type=>$html) {
                            $T->set_var('button', $html);
                            $T->parse('Btn', 'BtnBlock', true);
                        }
                    }
                    $T->clear_var('Btn');
                    $prodrows++;
                    $T->parse('PI', 'ProductItems', true);
                }   // foreach plugin_data

            }   // foreach $_PLUGINS

        }   // if page == 1

        //$T->parse('output', 'wrapper');
        $display .= $T->parse('', 'wrapper');

        if ($catrows == 0 && COM_isAnonUser()) {
            $T->set_var('anon_and_empty', 'true');
        }

        $pagenav_args = empty($pagenav_args) ? '' : '?'.implode('&', $pagenav_args);
        // Display pagination
        if ($prod_per_page > 0 && $count > $prod_per_page) {
            $T->set_var(
                'pagination',
                COM_printPageNavigation(
                    SHOP_URL . '/index.php' . $pagenav_args,
                    $page,
                    ceil($count / $prod_per_page)
                )
            );
        } else {
            $T->set_var('pagination', '');
        }

        // Display a "not found" message if count == 0
        if ($prodrows == 0) {
            $T->set_var('no_rows', true);
        }

        $display .= $T->parse('', 'end');
        return $display;
    }

}

?>