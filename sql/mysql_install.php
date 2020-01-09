<?php
/**
 * Database creation and update statements for the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_TABLES, $_SQL, $SHOP_UPGRADE, $_SHOP_SAMPLEDATA;
$SHOP_UPGRADE = array();

$_SQL = array(
'shop.ipnlog' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.ipnlog']} (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_addr` varchar(15) NOT NULL,
  `ts` int(11) unsigned DEFAULT NULL,
  `verified` tinyint(1) DEFAULT '0',
  `txn_id` varchar(255) DEFAULT NULL,
  `gateway` varchar(25) DEFAULT NULL,
  `ipn_data` text NOT NULL,
  `order_id` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ipnlog_ts` (`ts`),
  KEY `ipnlog_txnid` (`txn_id`)
) ENGINE=MyISAM",

'shop.products' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.products']} (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `description` text,
  `keywords` varchar(255) DEFAULT '',
  `price` decimal(12,4) unsigned DEFAULT NULL,
  `prod_type` tinyint(2) DEFAULT '0',
  `file` varchar(255) DEFAULT NULL,
  `expiration` int(11) DEFAULT NULL,
  `enabled` tinyint(1) DEFAULT '1',
  `featured` tinyint(1) unsigned DEFAULT '0',
  `dt_add` datetime NOT NULL,
  `views` int(4) unsigned DEFAULT '0',
  `comments_enabled` tinyint(1) DEFAULT '0',
  `rating_enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `buttons` text,
  `rating` double(6,4) NOT NULL DEFAULT '0.0000',
  `votes` int(11) unsigned NOT NULL DEFAULT '0',
  `weight` decimal(9,4) DEFAULT '0.0000',
  `taxable` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `shipping_type` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `shipping_amt` decimal(9,4) unsigned NOT NULL DEFAULT '0.0000',
  `shipping_units` decimal(9,4) unsigned NOT NULL DEFAULT '0.0000',
  `show_random` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `show_popular` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `options` text,
  `track_onhand` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `onhand` int(10) unsigned NOT NULL DEFAULT '0',
  `reorder` int(10) unsigned NOT NULL DEFAULT '0',
  `oversell` tinyint(1) NOT NULL DEFAULT '0',
  `qty_discounts` text,
  `custom` varchar(255) NOT NULL DEFAULT '',
  `avail_beg` date DEFAULT '1900-01-01',
  `avail_end` date DEFAULT '9999-12-31',
  `brand` varchar(255) NOT NULL DEFAULT '',
  `min_ord_qty` int(3) NOT NULL DEFAULT 1,
  `max_ord_qty` int(3) NOT NULL DEFAULT 0,
  `brand_id` int(11) unsigned NOT NULL DEFAULT 0,
  `supplier_id` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `products_name` (`name`),
  KEY `products_price` (`price`),
  KEY `avail_beg` (`avail_beg`),
  KEY `avail_end` (`avail_end`)
) ENGINE=MyISAM",

'shop.orderitems' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.orderitems']} (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(40) NOT NULL,
  `product_id` varchar(128) NOT NULL,
  `variant_id` int(11) unsigned NOT NULL DEFAULT '0',
  `description` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1',
  `txn_id` varchar(128) DEFAULT '',
  `txn_type` varchar(255) DEFAULT '',
  `expiration` int(11) unsigned NOT NULL DEFAULT '0',
  `base_price` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `price` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `qty_discount` decimal(5,2) NOT NULL DEFAULT '0.00',
  `net_price` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `taxable` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `token` varchar(40) NOT NULL DEFAULT '',
  `options` varchar(40) DEFAULT '',
  `options_text` text,
  `extras` text,
  `shipping` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `handling` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `tax` decimal(9,4) NOT NULL DEFAULT '0.0000',
  `tax_rate` decimal(6,4) NOT NULL DEFAULT  '0.0000',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `purchases_productid` (`product_id`),
  KEY `purchases_txnid` (`txn_id`)
) ENGINE=MyISAM",

'shop.images' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.images']} (
  `img_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) unsigned NOT NULL,
  `orderby` int(3) NOT NULL DEFAULT 999,
  `filename` varchar(255) DEFAULT NULL,
  `nonce` varchar(20) DEFAULT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`img_id`),
  KEY `idxProd` (`product_id`,`img_id`)
) ENGINE=MyISAM",

'shop.categories' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.categories']} (
  `cat_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` smallint(5) unsigned DEFAULT '0',
  `cat_name` varchar(128) DEFAULT '',
  `description` text,
  `enabled` tinyint(1) unsigned DEFAULT '1',
  `grp_access` mediumint(8) unsigned NOT NULL DEFAULT '1',
  `image` varchar(255) DEFAULT '',
  `google_taxonomy` text,
  `lft` smallint(5) unsigned NOT NULL DEFAULT '0',
  `rgt` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`cat_id`),
  KEY `idxName` (`cat_name`,`cat_id`),
  KEY `cat_lft` (`lft`),
  KEY `cat_rgt` (`rgt`)
) ENGINE=MyISAM",

'shop.prod_opt_vals' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.prod_opt_vals']}` (
  `pov_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pog_id` int(11) unsigned NOT NULL DEFAULT '0',
  `item_id` int(11) unsigned DEFAULT NULL,
  `pov_value` varchar(64) DEFAULT NULL,
  `orderby` int(3) NOT NULL DEFAULT '0',
  `pov_price` decimal(9,4) DEFAULT NULL,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `sku` varchar(8) DEFAULT NULL,
  PRIMARY KEY (`pov_id`),
  UNIQUE KEY `pog_value` (`pog_id`, `pov_value`)
) ENGINE=MyISAM",

'shop.buttons' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.buttons']}` (
  `pi_name` varchar(20) NOT NULL DEFAULT 'shop',
  `item_id` varchar(40) NOT NULL,
  `gw_name` varchar(10) NOT NULL DEFAULT '',
  `btn_key` varchar(20) NOT NULL,
  `button` text,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pi_name`,`item_id`,`gw_name`,`btn_key`)
) ENGINE=MyISAM",

'shop.orders' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.orders']}` (
  `order_id` varchar(40) NOT NULL,
  `uid` int(11) NOT NULL DEFAULT '0',
  `order_date` int(11) unsigned NOT NULL DEFAULT '0',
  `last_mod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `billto_id` int(11) unsigned NOT NULL DEFAULT '0',
  `billto_name` varchar(255) DEFAULT NULL,
  `billto_company` varchar(255) DEFAULT NULL,
  `billto_address1` varchar(255) DEFAULT NULL,
  `billto_address2` varchar(255) DEFAULT NULL,
  `billto_city` varchar(255) DEFAULT NULL,
  `billto_state` varchar(255) DEFAULT NULL,
  `billto_country` varchar(255) DEFAULT NULL,
  `billto_zip` varchar(40) DEFAULT NULL,
  `shipto_id` int(11) unsigned NOT NULL DEFAULT '0',
  `shipto_name` varchar(255) DEFAULT NULL,
  `shipto_company` varchar(255) DEFAULT NULL,
  `shipto_address1` varchar(255) DEFAULT NULL,
  `shipto_address2` varchar(255) DEFAULT NULL,
  `shipto_city` varchar(255) DEFAULT NULL,
  `shipto_state` varchar(255) DEFAULT NULL,
  `shipto_country` varchar(255) DEFAULT NULL,
  `shipto_zip` varchar(40) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `buyer_email` varchar(255) DEFAULT NULL,
  `gross_items` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `net_nontax` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `net_taxable` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `order_total` decimal(12,4) unsigned DEFAULT '0.0000',
  `tax` decimal(9,4) unsigned DEFAULT NULL,
  `shipping` decimal(9,4) unsigned DEFAULT NULL,
  `handling` decimal(9,4) unsigned DEFAULT NULL,
  `by_gc` decimal(12,4) unsigned DEFAULT NULL,
  `status` varchar(25) DEFAULT 'pending',
  `pmt_method` varchar(20) DEFAULT NULL,
  `pmt_txn_id` varchar(255) DEFAULT NULL,
  `instructions` text,
  `token` varchar(20) DEFAULT NULL,
  `tax_rate` decimal(7,5) NOT NULL DEFAULT '0.00000',
  `info` text,
  `currency` varchar(5) NOT NULL DEFAULT 'USD',
  `order_seq` int(11) unsigned DEFAULT NULL,
  `shipper_id` int(3) unsigned DEFAULT '0',
  `discount_code` varchar(20) DEFAULT NULL,
  `discount_pct` decimal(4,2) DEFAULT '0.00',
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `order_seq` (`order_seq`),
  KEY `order_date` (`order_date`)
) ENGINE=MyISAM",

'shop.address' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.address']}` (
  `addr_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '1',
  `name` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `address1` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `zip` varchar(40) DEFAULT NULL,
  `billto_def` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `shipto_def` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`addr_id`),
  KEY `uid` (`uid`,`zip`)
) ENGINE=MyISAM",

'shop.userinfo' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.userinfo']}` (
  `uid` int(11) unsigned NOT NULL,
  `cart` text,
  `pref_gw` varchar(12) NOT NULL DEFAULT '',
  PRIMARY KEY (`uid`)
) ENGINE=MyISAM",

'shop.gateways' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.gateways']}` (
  `id` varchar(25) NOT NULL,
  `orderby` int(3) NOT NULL DEFAULT '0',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `description` varchar(255) DEFAULT NULL,
  `config` text,
  `services` varchar(255) DEFAULT NULL,
  `grp_access` int(3) unsigned NOT NULL DEFAULT '2',
  PRIMARY KEY (`id`),
  KEY `orderby` (`orderby`)
) ENGINE=MyISAM",

'shop.workflows' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.workflows']}` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `wf_name` varchar(40) DEFAULT NULL,
  `orderby` int(2) DEFAULT NULL,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `can_disable` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `orderby` (`orderby`)
) ENGINE=MyISAM",

'shop.orderstatus' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.orderstatus']}` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `orderby` int(3) unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `name` varchar(20) NOT NULL,
  `notify_buyer` tinyint(1) NOT NULL DEFAULT '1',
  `notify_admin` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `orderby` (`orderby`)
) ENGINE=MyISAM",

'shop.order_log' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.order_log']}` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ts` int(11) unsigned DEFAULT NULL,
  `order_id` varchar(40) DEFAULT NULL,
  `username` varchar(60) NOT NULL DEFAULT '',
  `message` text,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=MyISAM",

'shop.currency' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.currency']}` (
  `code` varchar(3) NOT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `numeric_code` int(4) DEFAULT NULL,
  `symbol_placement` varchar(10) DEFAULT NULL,
  `symbol_spacer` varchar(2) DEFAULT ' ',
  `code_placement` varchar(10) DEFAULT 'after',
  `decimals` int(3) DEFAULT '2',
  `rounding_step` float(5,2) DEFAULT '0.00',
  `thousands_sep` varchar(2) DEFAULT ',',
  `decimal_sep` varchar(2) DEFAULT '.',
  `major_unit` varchar(20) DEFAULT NULL,
  `minor_unit` varchar(20) DEFAULT NULL,
  `conversion_rate` float(7,5) DEFAULT '1.00000',
  `conversion_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`)
) ENGINE=MyISAM",

'shop.coupons' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.coupons']}` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(128) NOT NULL,
  `amount` decimal(12,4) unsigned NOT NULL DEFAULT '0.0000',
  `balance` decimal(12,4) unsigned NOT NULL DEFAULT '0.0000',
  `buyer` int(11) unsigned NOT NULL DEFAULT '0',
  `redeemer` int(11) unsigned NOT NULL DEFAULT '0',
  `purchased` int(11) unsigned NOT NULL DEFAULT '0',
  `redeemed` int(11) unsigned NOT NULL DEFAULT '0',
  `expires` date DEFAULT '9999-12-31',
  `status` varchar(10) NOT NULL DEFAULT 'valid',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `owner` (`redeemer`,`balance`,`expires`),
  KEY `purchased` (`purchased`)
) ENGINE=MyIsam",

'shop.coupon_log' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.coupon_log']} (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `code` varchar(128) NOT NULL,
  `ts` int(11) unsigned DEFAULT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `amount` float(8,2) DEFAULT NULL,
  `msg` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `code` (`code`)
) ENGINE=MyIsam",

'shop.sales' => "CREATE TABLE IF NOT EXISTS {$_TABLES['shop.sales']} (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(40) DEFAULT NULL,
  `item_type` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `item_id` int(11) unsigned NOT NULL,
  `start` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `end` datetime NOT NULL DEFAULT '9999-12-31 23:59:59',
  `discount_type` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `amount` decimal(6,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_type` (`item_type`,`item_id`)
) ENGINE=MyIsam",

'shop.shipping' => "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.shipping']}` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `module_code` varchar(10) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `min_units` int(11) unsigned NOT NULL DEFAULT '0',
  `max_units` int(11) unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `valid_from` int(11) unsigned NOT NULL DEFAULT '0',
  `valid_to` int(11) unsigned NOT NULL DEFAULT '2145902399',
  `use_fixed` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `rates` text,
  `grp_access` int(3) unsigned NOT NULL DEFAULT '2',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM",
"CREATE TABLE `{$_TABLES['shop.countries']}` (
  `country_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `region_id` int(11) unsigned NOT NULL DEFAULT '0',
  `currency_code` varchar(4) NOT NULL DEFAULT '',
  `iso_code` varchar(3) NOT NULL DEFAULT '',
  `country_name` varchar(127) NOT NULL DEFAULT '',
  `dial_code` int(4) unsigned NOT NULL DEFAULT '0',
  `country_enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`country_id`),
  KEY `iso_code` (`iso_code`),
  KEY `zone_id` (`region_id`)
) ENGINE=MyISAM",
"CREATE TABLE `{$_TABLES['shop.regions']}` (
  `region_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `region_name` varchar(64) NOT NULL DEFAULT '',
  `region_enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`region_id`)
) ENGINE=MyISAM",
"CREATE TABLE `{$_TABLES['shop.states']}` (
  `state_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `country_id` int(11) unsigned NOT NULL DEFAULT '0',
  `state_name` varchar(64) NOT NULL DEFAULT '',
  `iso_code` varchar(10) NOT NULL DEFAULT '',
  `state_enabled` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`state_id`)
) ENGINE=MyISAM",
);

// Sample data to load up the Shop gateway configuration
$_SHOP_SAMPLEDATA = array(
    "INSERT INTO {$_TABLES['shop.categories']}
            (cat_id, parent_id, cat_name, description, grp_access, lft, rgt)
        VALUES
            (1, 0, 'Home', 'Root Category', 2, 1, 2)",
    "INSERT INTO {$_TABLES['shop.workflows']}
            (id, wf_name, orderby, enabled, can_disable)
        VALUES
            (1, 'viewcart', 10, 3, 0),
            (2, 'billto', 20, 0, 1),
            (3, 'shipto', 30, 2, 1)",
    "INSERT INTO {$_TABLES['shop.orderstatus']}
            (id, orderby, enabled, name, notify_buyer, notify_admin)
        VALUES
            (1, 10, 1, 'pending', 0, 0),
            (2, 20, 1, 'paid', 1, 1),
            (3, 30, 1, 'processing', 1, 0),
            (4, 40, 1, 'shipped', 1, 0),
            (5, 50, 1, 'closed', 0, 0),
            (6, 60, 1, 'refunded', 0, 0)",
    "INSERT INTO `{$_TABLES['shop.currency']}` VALUES
        ('AED','?.?','United Arab Emirates Dirham',784,'hidden',' ','before',2,0.00,',','.','Dirham','Fils',1.00000,'2014-01-03 20:51:17'),
    ('AFN','Af','Afghan Afghani',971,'hidden',' ','after',0,0.00,',','.','Afghani','Pul',1.00000,'2014-01-03 20:54:44'),
	('ANG','NAf.','Netherlands Antillean Guilder',532,'hidden',' ','after',2,0.00,',','.','Guilder','Cent',1.00000,'2014-01-03 20:54:44'),
	('AOA','Kz','Angolan Kwanza',973,'hidden',' ','after',2,0.00,',','.','Kwanza','Cêntimo',1.00000,'2014-01-03 20:54:44'),
	('ARM','m\$n','Argentine Peso Moneda Nacional',NULL,'hidden',' ','after',2,0.00,',','.','Peso','Centavos',1.00000,'2014-01-03 20:54:44'),
	('ARS','AR$','Argentine Peso',32,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('AUD','$','Australian Dollar',36,'before',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('AWG','Afl.','Aruban Florin',533,'hidden',' ','after',2,0.00,',','.','Guilder','Cent',1.00000,'2014-01-03 20:54:44'),
	('AZN','man.','Azerbaijanian Manat',NULL,'hidden',' ','after',2,0.00,',','.','New Manat','Q?pik',1.00000,'2014-01-03 20:54:44'),
	('BAM','KM','Bosnia-Herzegovina Convertible Mark',977,'hidden',' ','after',2,0.00,',','.','Convertible Marka','Fening',1.00000,'2014-01-03 20:54:44'),
	('BBD','Bds$','Barbadian Dollar',52,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('BDT','Tk','Bangladeshi Taka',50,'hidden',' ','after',2,0.00,',','.','Taka','Paisa',1.00000,'2014-01-03 20:54:44'),
	('BGN','??','Bulgarian lev',975,'after',' ','hidden',2,0.00,',',',','Lev','Stotinka',1.00000,'2014-01-03 20:49:55'),
	('BHD','BD','Bahraini Dinar',48,'hidden',' ','after',3,0.00,',','.','Dinar','Fils',1.00000,'2014-01-03 20:54:44'),
	('BIF','FBu','Burundian Franc',108,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('BMD','BD$','Bermudan Dollar',60,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('BND','BN$','Brunei Dollar',96,'hidden',' ','after',2,0.00,',','.','Dollar','Sen',1.00000,'2014-01-03 20:54:44'),
	('BOB','Bs','Bolivian Boliviano',68,'hidden',' ','after',2,0.00,',','.','Bolivianos','Centavo',1.00000,'2014-01-03 20:54:44'),
	('BRL','R$','Brazilian Real',986,'before',' ','hidden',2,0.00,'.',',','Reais','Centavo',1.00000,'2014-01-03 20:49:55'),
	('BSD','BS$','Bahamian Dollar',44,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('BTN','Nu.','Bhutanese Ngultrum',64,'hidden',' ','after',2,0.00,',','.','Ngultrum','Chetrum',1.00000,'2014-01-03 20:54:44'),
	('BWP','BWP','Botswanan Pula',72,'hidden',' ','after',2,0.00,',','.','Pulas','Thebe',1.00000,'2014-01-03 20:54:44'),
	('BYR','???.','Belarusian ruble',974,'after',' ','hidden',0,0.00,',','.','Ruble',NULL,1.00000,'2014-01-03 20:49:48'),
	('BZD','BZ$','Belize Dollar',84,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('CAD','CA$','Canadian Dollar',124,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('CDF','CDF','Congolese Franc',976,'hidden',' ','after',2,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('CHF','Fr.','Swiss Franc',756,'hidden',' ','after',2,0.05,',','.','Franc','Rappen',1.00000,'2014-01-03 20:54:44'),
	('CLP','CL$','Chilean Peso',152,'hidden',' ','after',0,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('CNY','¥','Chinese Yuan Renminbi',156,'before',' ','hidden',2,0.00,',','.','Yuan','Fen',1.00000,'2014-01-03 20:49:55'),
	('COP','$','Colombian Peso',170,'before',' ','hidden',0,0.00,'.',',','Peso','Centavo',1.00000,'2014-01-03 20:49:48'),
	('CRC','¢','Costa Rican Colón',188,'hidden',' ','after',0,0.00,',','.','Colón','Céntimo',1.00000,'2014-01-03 20:54:44'),
	('CUC','CUC$','Cuban Convertible Peso',NULL,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('CUP','CU$','Cuban Peso',192,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('CVE','CV$','Cape Verdean Escudo',132,'hidden',' ','after',2,0.00,',','.','Escudo','Centavo',1.00000,'2014-01-03 20:54:44'),
	('CZK','K?','Czech Republic Koruna',203,'after',' ','hidden',2,0.00,',',',','Koruna','Halé?',1.00000,'2014-01-03 20:49:55'),
	('DJF','Fdj','Djiboutian Franc',262,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('DKK','kr.','Danish Krone',208,'after',' ','hidden',2,0.00,',',',','Kroner','Øre',1.00000,'2014-01-03 20:49:55'),
	('DOP','RD$','Dominican Peso',214,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('DZD','DA','Algerian Dinar',12,'hidden',' ','after',2,0.00,',','.','Dinar','Santeem',1.00000,'2014-01-03 20:54:44'),
	('EEK','Ekr','Estonian Kroon',233,'hidden',' ','after',2,0.00,',',',','Krooni','Sent',1.00000,'2014-01-03 20:54:44'),
	('EGP','EG£','Egyptian Pound',818,'hidden',' ','after',2,0.00,',','.','Pound','Piastr',1.00000,'2014-01-03 20:54:44'),
	('ERN','Nfk','Eritrean Nakfa',232,'hidden',' ','after',2,0.00,',','.','Nakfa','Cent',1.00000,'2014-01-03 20:54:44'),
	('ETB','Br','Ethiopian Birr',230,'hidden',' ','after',2,0.00,',','.','Birr','Santim',1.00000,'2014-01-03 20:54:44'),
	('EUR','€','Euro',978,'after',' ','hidden',2,0.00,',',',','Euro','Cent',1.00000,'2014-01-03 20:49:55'),
	('FJD','FJ$','Fijian Dollar',242,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('FKP','FK£','Falkland Islands Pound',238,'hidden',' ','after',2,0.00,',','.','Pound','Penny',1.00000,'2014-01-03 20:54:44'),
	('GBP','£','British Pound Sterling',826,'before',' ','hidden',2,0.00,',','.','Pound','Penny',1.00000,'2014-01-03 20:49:55'),
	('GHS','GH?','Ghanaian Cedi',NULL,'hidden',' ','after',2,0.00,',','.','Cedi','Pesewa',1.00000,'2014-01-03 20:54:44'),
	('GIP','GI£','Gibraltar Pound',292,'hidden',' ','after',2,0.00,',','.','Pound','Penny',1.00000,'2014-01-03 20:54:44'),
	('GMD','GMD','Gambian Dalasi',270,'hidden',' ','after',2,0.00,',','.','Dalasis','Butut',1.00000,'2014-01-03 20:54:44'),
	('GNF','FG','Guinean Franc',324,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('GTQ','GTQ','Guatemalan Quetzal',320,'hidden',' ','after',2,0.00,',','.','Quetzales','Centavo',1.00000,'2014-01-03 20:54:44'),
	('GYD','GY$','Guyanaese Dollar',328,'hidden',' ','after',0,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('HKD','HK$','Hong Kong Dollar',344,'before',' ','hidden',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:49:55'),
	('HNL','HNL','Honduran Lempira',340,'hidden',' ','after',2,0.00,',','.','Lempiras','Centavo',1.00000,'2014-01-03 20:54:44'),
	('HRK','kn','Croatian Kuna',191,'hidden',' ','after',2,0.00,',','.','Kuna','Lipa',1.00000,'2014-01-03 20:54:44'),
	('HTG','HTG','Haitian Gourde',332,'hidden',' ','after',2,0.00,',','.','Gourde','Centime',1.00000,'2014-01-03 20:54:44'),
	('HUF','Ft','Hungarian Forint',348,'after',' ','hidden',0,0.00,',',',','Forint',NULL,1.00000,'2014-01-03 20:49:48'),
	('IDR','Rp','Indonesian Rupiah',360,'hidden',' ','after',0,0.00,',','.','Rupiahs','Sen',1.00000,'2014-01-03 20:54:44'),
	('ILS','?','Israeli New Shekel',376,'before',' ','hidden',2,0.00,',','.','New Shekels','Agora',1.00000,'2014-01-03 20:49:55'),
	('INR','Rs','Indian Rupee',356,'hidden',' ','after',2,0.00,',','.','Rupee','Paisa',1.00000,'2014-01-03 20:54:44'),
	('IRR','?','Iranian Rial',364,'after',' ','hidden',2,0.00,',','.','Toman','Rial',1.00000,'2014-01-03 20:49:55'),
	('ISK','Ikr','Icelandic Króna',352,'hidden',' ','after',0,0.00,',','.','Kronur','Eyrir',1.00000,'2014-01-03 20:54:44'),
	('JMD','J$','Jamaican Dollar',388,'before',' ','hidden',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:49:55'),
	('JOD','JD','Jordanian Dinar',400,'hidden',' ','after',3,0.00,',','.','Dinar','Piastr',1.00000,'2014-01-03 20:54:44'),
	('JPY','¥','Japanese Yen',392,'before',' ','hidden',0,0.00,',','.','Yen','Sen',1.00000,'2014-01-03 20:49:48'),
	('KES','Ksh','Kenyan Shilling',404,'hidden',' ','after',2,0.00,',','.','Shilling','Cent',1.00000,'2014-01-03 20:54:44'),
	('KGS','???','Kyrgyzstani Som',417,'after',' ','hidden',2,0.00,',','.','Som','Tyiyn',1.00000,'2014-01-03 20:49:55'),
	('KMF','CF','Comorian Franc',174,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('KRW','?','South Korean Won',410,'hidden',' ','after',0,0.00,',','.','Won','Jeon',1.00000,'2014-01-03 20:54:44'),
	('KWD','KD','Kuwaiti Dinar',414,'hidden',' ','after',3,0.00,',','.','Dinar','Fils',1.00000,'2014-01-03 20:54:44'),
	('KYD','KY$','Cayman Islands Dollar',136,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('KZT','??.','Kazakhstani tenge',398,'after',' ','hidden',2,0.00,',',',','Tenge','Tiyn',1.00000,'2014-01-03 20:49:55'),
	('LAK','?N','Laotian Kip',418,'hidden',' ','after',0,0.00,',','.','Kips','Att',1.00000,'2014-01-03 20:54:44'),
	('LBP','LB£','Lebanese Pound',422,'hidden',' ','after',0,0.00,',','.','Pound','Piastre',1.00000,'2014-01-03 20:54:44'),
	('LKR','SLRs','Sri Lanka Rupee',144,'hidden',' ','after',2,0.00,',','.','Rupee','Cent',1.00000,'2014-01-03 20:54:44'),
	('LRD','L$','Liberian Dollar',430,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('LSL','LSL','Lesotho Loti',426,'hidden',' ','after',2,0.00,',','.','Loti','Sente',1.00000,'2014-01-03 20:54:44'),
	('LTL','Lt','Lithuanian Litas',440,'hidden',' ','after',2,0.00,',','.','Litai','Centas',1.00000,'2014-01-03 20:54:44'),
	('LVL','Ls','Latvian Lats',428,'hidden',' ','after',2,0.00,',','.','Lati','Santims',1.00000,'2014-01-03 20:54:44'),
	('LYD','LD','Libyan Dinar',434,'hidden',' ','after',3,0.00,',','.','Dinar','Dirham',1.00000,'2014-01-03 20:54:44'),
	('MAD',' Dhs','Moroccan Dirham',504,'after',' ','hidden',2,0.00,',','.','Dirhams','Santimat',1.00000,'2014-01-03 20:49:55'),
	('MDL','MDL','Moldovan leu',498,'after',' ','hidden',2,0.00,',','.','Lei','bani',1.00000,'2014-01-03 20:49:55'),
	('MMK','MMK','Myanma Kyat',104,'hidden',' ','after',0,0.00,',','.','Kyat','Pya',1.00000,'2014-01-03 20:54:44'),
	('MNT','?','Mongolian Tugrik',496,'hidden',' ','after',0,0.00,',','.','Tugriks','Möngö',1.00000,'2014-01-03 20:54:44'),
	('MOP','MOP$','Macanese Pataca',446,'hidden',' ','after',2,0.00,',','.','Pataca','Avo',1.00000,'2014-01-03 20:54:44'),
	('MRO','UM','Mauritanian Ouguiya',478,'hidden',' ','after',0,0.00,',','.','Ouguiya','Khoums',1.00000,'2014-01-03 20:54:44'),
	('MTP','MT£','Maltese Pound',NULL,'hidden',' ','after',2,0.00,',','.','Pound','Shilling',1.00000,'2014-01-03 20:54:44'),
	('MUR','MURs','Mauritian Rupee',480,'hidden',' ','after',0,0.00,',','.','Rupee','Cent',1.00000,'2014-01-03 20:54:44'),
	('MXN','$','Mexican Peso',484,'before',' ','hidden',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:49:55'),
	('MYR','RM','Malaysian Ringgit',458,'before',' ','hidden',2,0.00,',','.','Ringgits','Sen',1.00000,'2014-01-03 20:49:55'),
	('MZN','MTn','Mozambican Metical',NULL,'hidden',' ','after',2,0.00,',','.','Metical','Centavo',1.00000,'2014-01-03 20:54:44'),
	('NAD','N$','Namibian Dollar',516,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('NGN','?','Nigerian Naira',566,'hidden',' ','after',2,0.00,',','.','Naira','Kobo',1.00000,'2014-01-03 20:54:44'),
	('NIO','C$','Nicaraguan Cordoba Oro',558,'hidden',' ','after',2,0.00,',','.','Cordoba','Centavo',1.00000,'2014-01-03 20:54:44'),
	('NOK','Nkr','Norwegian Krone',578,'hidden',' ','after',2,0.00,',',',','Krone','Øre',1.00000,'2014-01-03 20:54:44'),
	('NPR','NPRs','Nepalese Rupee',524,'hidden',' ','after',2,0.00,',','.','Rupee','Paisa',1.00000,'2014-01-03 20:54:44'),
	('NZD','NZ$','New Zealand Dollar',554,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('PAB','B/.','Panamanian Balboa',590,'hidden',' ','after',2,0.00,',','.','Balboa','Centésimo',1.00000,'2014-01-03 20:54:44'),
	('PEN','S/.','Peruvian Nuevo Sol',604,'before',' ','hidden',2,0.00,',','.','Nuevos Sole','Céntimo',1.00000,'2014-01-03 20:49:55'),
	('PGK','PGK','Papua New Guinean Kina',598,'hidden',' ','after',2,0.00,',','.','Kina ','Toea',1.00000,'2014-01-03 20:54:44'),
	('PHP','?','Philippine Peso',608,'hidden',' ','after',2,0.00,',','.','Peso','Centavo',1.00000,'2014-01-03 20:54:44'),
	('PKR','PKRs','Pakistani Rupee',586,'hidden',' ','after',0,0.00,',','.','Rupee','Paisa',1.00000,'2014-01-03 20:54:44'),
	('PLN','z?','Polish Z?oty',985,'after',' ','hidden',2,0.00,',',',','Z?otych','Grosz',1.00000,'2014-01-03 20:49:55'),
	('PYG','?','Paraguayan Guarani',600,'hidden',' ','after',0,0.00,',','.','Guarani','Céntimo',1.00000,'2014-01-03 20:54:44'),
	('QAR','QR','Qatari Rial',634,'hidden',' ','after',2,0.00,',','.','Rial','Dirham',1.00000,'2014-01-03 20:54:44'),
	('RHD','RH$','Rhodesian Dollar',NULL,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('RON','RON','Romanian Leu',NULL,'hidden',' ','after',2,0.00,',','.','Leu','Ban',1.00000,'2014-01-03 20:54:44'),
	('RSD','din.','Serbian Dinar',NULL,'hidden',' ','after',0,0.00,',','.','Dinars','Para',1.00000,'2014-01-03 20:54:44'),
	('RUB','???.','Russian Ruble',643,'after',' ','hidden',2,0.00,',',',','Ruble','Kopek',1.00000,'2014-01-03 20:49:55'),
	('SAR','SR','Saudi Riyal',682,'hidden',' ','after',2,0.00,',','.','Riyals','Hallallah',1.00000,'2014-01-03 20:54:44'),
	('SBD','SI$','Solomon Islands Dollar',90,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('SCR','SRe','Seychellois Rupee',690,'hidden',' ','after',2,0.00,',','.','Rupee','Cent',1.00000,'2014-01-03 20:54:44'),
	('SDD','LSd','Old Sudanese Dinar',736,'hidden',' ','after',2,0.00,',','.','Dinar','None',1.00000,'2014-01-03 20:54:44'),
	('SEK','kr','Swedish Krona',752,'after',' ','hidden',2,0.00,',',',','Kronor','Öre',1.00000,'2014-01-03 20:49:55'),
	('SGD','S$','Singapore Dollar',702,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('SHP','SH£','Saint Helena Pound',654,'hidden',' ','after',2,0.00,',','.','Pound','Penny',1.00000,'2014-01-03 20:54:44'),
	('SLL','Le','Sierra Leonean Leone',694,'hidden',' ','after',0,0.00,',','.','Leone','Cent',1.00000,'2014-01-03 20:54:44'),
	('SOS','Ssh','Somali Shilling',706,'hidden',' ','after',0,0.00,',','.','Shilling','Cent',1.00000,'2014-01-03 20:54:44'),
	('SRD','SR$','Surinamese Dollar',NULL,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('SRG','Sf','Suriname Guilder',740,'hidden',' ','after',2,0.00,',','.','Guilder','Cent',1.00000,'2014-01-03 20:54:44'),
	('STD','Db','São Tomé and Príncipe Dobra',678,'hidden',' ','after',0,0.00,',','.','Dobra','Cêntimo',1.00000,'2014-01-03 20:54:44'),
	('SYP','SY£','Syrian Pound',760,'hidden',' ','after',0,0.00,',','.','Pound','Piastre',1.00000,'2014-01-03 20:54:44'),
	('SZL','SZL','Swazi Lilangeni',748,'hidden',' ','after',2,0.00,',','.','Lilangeni','Cent',1.00000,'2014-01-03 20:54:44'),
	('THB','?','Thai Baht',764,'hidden',' ','after',2,0.00,',','.','Baht','Satang',1.00000,'2014-01-03 20:54:44'),
	('TND','DT','Tunisian Dinar',788,'hidden',' ','after',3,0.00,',','.','Dinar','Millime',1.00000,'2014-01-03 20:54:44'),
	('TOP','T$','Tongan Pa?anga',776,'hidden',' ','after',2,0.00,',','.','Pa?anga','Senit',1.00000,'2014-01-03 20:54:44'),
	('TRY','TL','Turkish Lira',949,'after',' ','',2,0.00,'.',',','Lira','Kurus',1.00000,'2014-01-03 20:49:55'),
	('TTD','TT$','Trinidad and Tobago Dollar',780,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('TWD','NT$','New Taiwan Dollar',901,'hidden',' ','after',2,0.00,',','.','New Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('TZS','TSh','Tanzanian Shilling',834,'hidden',' ','after',0,0.00,',','.','Shilling','Senti',1.00000,'2014-01-03 20:54:44'),
	('UAH','???.','Ukrainian Hryvnia',980,'after',' ','hidden',2,0.00,',','.','Hryvnia','Kopiyka',1.00000,'2014-01-03 20:49:55'),
	('UGX','USh','Ugandan Shilling',800,'hidden',' ','after',0,0.00,',','.','Shilling','Cent',1.00000,'2014-01-03 20:54:44'),
	('USD','$','United States Dollar',840,'before',' ','hidden',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:49:55'),
	('UYU','\$U','Uruguayan Peso',858,'hidden',' ','after',2,0.00,',','.','Peso','Centésimo',1.00000,'2014-01-03 20:54:44'),
	('VEF','Bs.F.','Venezuelan Bolívar Fuerte',NULL,'hidden',' ','after',2,0.00,',','.','Bolivares Fuerte','Céntimo',1.00000,'2014-01-03 20:54:44'),
	('VND','?','Vietnamese Dong',704,'after','','hidden',0,0.00,'.','.','Dong','Hà',1.00000,'2014-01-03 20:53:33'),
	('VUV','VT','Vanuatu Vatu',548,'hidden',' ','after',0,0.00,',','.','Vatu',NULL,1.00000,'2014-01-03 20:54:44'),
	('WST','WS$','Samoan Tala',882,'hidden',' ','after',2,0.00,',','.','Tala','Sene',1.00000,'2014-01-03 20:54:44'),
	('XAF','FCFA','CFA Franc BEAC',950,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('XCD','EC$','East Caribbean Dollar',951,'hidden',' ','after',2,0.00,',','.','Dollar','Cent',1.00000,'2014-01-03 20:54:44'),
	('XOF','CFA','CFA Franc BCEAO',952,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('XPF','CFPF','CFP Franc',953,'hidden',' ','after',0,0.00,',','.','Franc','Centime',1.00000,'2014-01-03 20:54:44'),
	('YER','YR','Yemeni Rial',886,'hidden',' ','after',0,0.00,',','.','Rial','Fils',1.00000,'2014-01-03 20:54:44'),
	('ZAR','R','South African Rand',710,'before',' ','hidden',2,0.00,',','.','Rand','Cent',1.00000,'2014-01-03 20:49:55'),
	('ZMK','ZK','Zambian Kwacha',894,'hidden',' ','after',0,0.00,',','.','Kwacha','Ngwee',1.00000,'2014-01-03 20:54:44');",
        "INSERT INTO `{$_TABLES['shop.shipping']}`
            (id, module_code, name, min_units, max_units, rates)
        VALUES
            (0, 'usps', 'USPS Priority Flat Rate', 0.0001, 50.0000, '[{\"dscp\":\"Small\",\"units\":5,\"rate\":7.2},{\"dscp\":\"Medium\",\"units\":20,\"rate\":13.65},{\"dscp\":\"Large\",\"units\":50,\"rate\":18.9}]')",
        "INSERT INTO `{$_TABLES['shop.regions']}` VALUES
            (1,'Europe',1),
            (2,'North America',1),
            (3,'Asia',1),
            (4,'Africa',1),
            (5,'Oceania',1),
            (6,'South America',1),
            (7,'Europe (non-EU)',1),
            (8,'Central America/Antilla',1)",
    "INSERT INTO `{$_TABLES['shop.countries']}` VALUES (1,1,'','DE','Germany',49,1),(2,1,'','AT','Austria',43,1),(3,1,'','BE','Belgium',32,1),(4,2,'CAD','CA','Canada',1,1),(5,3,'','CN','China',86,1),(6,1,'','ES','Spain',34,1),(7,1,'','FI','Finland',358,1),(8,1,'','FR','France',33,1),(9,1,'','GR','Greece',30,1),(10,1,'','IT','Italy',39,1),(11,3,'','JP','Japan',81,1),(12,1,'','LU','Luxemburg',352,1),(13,1,'','NL','Netherlands',31,1),(14,1,'','PL','Poland',48,1),(15,1,'','PT','Portugal',351,1),(16,1,'','CZ','Czech Republic',420,1),(17,1,'','GB','United Kingdom',44,1),(18,1,'','SE','Sweden',46,1),(19,7,'','CH','Switzerland',41,1),(20,1,'','DK','Denmark',45,1),(21,2,'USD','US','United States',1,1),(22,3,'','HK','HongKong',852,1),(23,7,'','NO','Norway',47,1),(24,5,'','AU','Australia',61,1),(25,3,'','SG','Singapore',65,1),(26,1,'','IE','Ireland',353,1),(27,5,'','NZ','New Zealand',64,1),(28,3,'','KR','South Korea',82,1),(29,3,'','IL','Israel',972,1),(30,4,'','ZA','South Africa',27,1),(31,4,'','NG','Nigeria',234,1),(32,4,'','CI','Ivory Coast',225,1),(33,4,'','TG','Togo',228,1),(34,6,'','BO','Bolivia',591,1),(35,4,'','MU','Mauritius',230,1),(36,1,'','RO','Romania',40,1),(37,1,'','SK','Slovakia',421,1),(38,4,'','DZ','Algeria',213,1),(39,2,'','AS','American Samoa',0,1),(40,7,'','AD','Andorra',376,1),(41,4,'','AO','Angola',244,1),(42,8,'','AI','Anguilla',0,1),(43,2,'','AG','Antigua and Barbuda',0,1),(44,6,'','AR','Argentina',54,1),(45,3,'','AM','Armenia',374,1),(46,8,'','AW','Aruba',297,1),(47,3,'','AZ','Azerbaijan',994,1),(48,2,'','BS','Bahamas',0,1),(49,3,'','BH','Bahrain',973,1),(50,3,'','BD','Bangladesh',880,1),(51,2,'','BB','Barbados',0,1),(52,7,'','BY','Belarus',0,1),(53,8,'','BZ','Belize',501,1),(54,4,'','BJ','Benin',229,1),(55,2,'','BM','Bermuda',0,1),(56,3,'','BT','Bhutan',975,1),(57,4,'','BW','Botswana',267,1),(58,6,'','BR','Brazil',55,1),(59,3,'','BN','Brunei',673,1),(60,4,'','BF','Burkina Faso',226,1),(61,3,'','MM','Burma (Myanmar)',95,1),(62,4,'','BI','Burundi',257,1),(63,3,'','KH','Cambodia',855,1),(64,4,'','CM','Cameroon',237,1),(65,4,'','CV','Cape Verde',238,1),(66,4,'','CF','Central African Republic',236,1),(67,4,'','TD','Chad',235,1),(68,6,'','CL','Chile',56,1),(69,6,'','CO','Colombia',57,1),(70,4,'','KM','Comoros',269,1),(71,4,'','CD','Congo, Dem. Republic',242,1),(72,4,'','CG','Congo, Republic',243,1),(73,8,'','CR','Costa Rica',506,1),(74,7,'','HR','Croatia',385,1),(75,8,'','CU','Cuba',53,1),(76,1,'','CY','Cyprus',357,1),(77,4,'','DJ','Djibouti',253,1),(78,8,'','DM','Dominica',0,1),(79,8,'','DO','Dominican Republic',0,1),(80,3,'','TL','East Timor',670,1),(81,6,'','EC','Ecuador',593,1),(82,4,'','EG','Egypt',20,1),(83,8,'','SV','El Salvador',503,1),(84,4,'','GQ','Equatorial Guinea',240,1),(85,4,'','ER','Eritrea',291,1),(86,1,'','EE','Estonia',372,1),(87,4,'','ET','Ethiopia',251,1),(88,8,'','FK','Falkland Islands',0,1),(89,7,'','FO','Faroe Islands',298,1),(90,5,'','FJ','Fiji',679,1),(91,4,'','GA','Gabon',241,1),(92,4,'','GM','Gambia',220,1),(93,3,'','GE','Georgia',995,1),(94,4,'','GH','Ghana',233,1),(95,8,'','GD','Grenada',0,1),(96,7,'','GL','Greenland',299,1),(97,7,'','GI','Gibraltar',350,1),(98,8,'','GP','Guadeloupe',590,1),(99,5,'','GU','Guam',0,1),(100,8,'','GT','Guatemala',502,1),(101,7,'','GG','Guernsey',0,1),(102,4,'','GN','Guinea',224,1),(103,4,'','GW','Guinea-Bissau',245,1),(104,6,'','GY','Guyana',592,1),(105,8,'','HT','Haiti',509,1),(106,5,'','HM','Heard Island and McDonald Islands',0,1),(107,7,'','VA','Vatican City State',379,1),(108,8,'','HN','Honduras',504,1),(109,7,'','IS','Iceland',354,1),(110,3,'','IN','India',91,1),(111,3,'','ID','Indonesia',62,1),(112,3,'','IR','Iran',98,1),(113,3,'','IQ','Iraq',964,1),(114,7,'','IM','Man Island',0,1),(115,8,'','JM','Jamaica',0,1),(116,7,'','JE','Jersey',0,1),(117,3,'','JO','Jordan',962,1),(118,3,'','KZ','Kazakhstan',7,1),(119,4,'','KE','Kenya',254,1),(120,5,'','KI','Kiribati',686,1),(121,3,'','KP','Korea, Dem. Republic of',850,1),(122,3,'','KW','Kuwait',965,1),(123,3,'','KG','Kyrgyzstan',996,1),(124,3,'','LA','Laos',856,1),(125,1,'','LV','Latvia',371,1),(126,3,'','LB','Lebanon',961,1),(127,4,'','LS','Lesotho',266,1),(128,4,'','LR','Liberia',231,1),(129,4,'','LY','Libya',218,1),(130,1,'','LI','Liechtenstein',423,1),(131,1,'','LT','Lithuania',370,1),(132,3,'','MO','Macau',853,1),(133,7,'','MK','Macedonia',389,1),(134,4,'','MG','Madagascar',261,1),(135,4,'','MW','Malawi',265,1),(136,3,'','MY','Malaysia',60,1),(137,3,'','MV','Maldives',960,1),(138,4,'','ML','Mali',223,1),(139,1,'','MT','Malta',356,1),(140,5,'','MH','Marshall Islands',692,1),(141,8,'','MQ','Martinique',596,1),(142,4,'','MR','Mauritania',222,1),(143,1,'','HU','Hungary',36,1),(144,4,'','YT','Mayotte',262,1),(145,2,'','MX','Mexico',52,1),(146,5,'','FM','Micronesia',691,1),(147,7,'','MD','Moldova',373,1),(148,7,'','MC','Monaco',377,1),(149,3,'','MN','Mongolia',976,1),(150,7,'','ME','Montenegro',382,1),(151,8,'','MS','Montserrat',0,1),(152,4,'','MA','Morocco',212,1),(153,4,'','MZ','Mozambique',258,1),(154,4,'','NA','Namibia',264,1),(155,5,'','NR','Nauru',674,1),(156,3,'','NP','Nepal',977,1),(157,8,'','AN','Netherlands Antilles',599,1),(158,5,'','NC','New Caledonia',687,1),(159,8,'','NI','Nicaragua',505,1),(160,4,'','NE','Niger',227,1),(161,5,'','NU','Niue',683,1),(162,5,'','NF','Norfolk Island',0,1),(163,5,'','MP','Northern Mariana Islands',0,1),(164,3,'','OM','Oman',968,1),(165,3,'','PK','Pakistan',92,1),(166,5,'','PW','Palau',680,1),(167,3,'','PS','Palestinian Territories',0,1),(168,8,'','PA','Panama',507,1),(169,5,'','PG','Papua New Guinea',675,1),(170,6,'','PY','Paraguay',595,1),(171,6,'','PE','Peru',51,1),(172,3,'','PH','Philippines',63,1),(173,5,'','PN','Pitcairn',0,1),(174,8,'','PR','Puerto Rico',0,1),(175,3,'','QA','Qatar',974,1),(176,4,'','RE','Reunion Island',262,1),(177,7,'','RU','Russian Federation',7,1),(178,4,'','RW','Rwanda',250,1),(179,8,'','BL','Saint Barthelemy',0,1),(180,8,'','KN','Saint Kitts and Nevis',0,1),(181,8,'','LC','Saint Lucia',0,1),(182,8,'','MF','Saint Martin',0,1),(183,8,'','PM','Saint Pierre and Miquelon',508,1),(184,8,'','VC','Saint Vincent and the Grenadines',0,1),(185,5,'','WS','Samoa',685,1),(186,7,'','SM','San Marino',378,1),(187,4,'','ST','São Tomé and Príncipe',239,1),(188,3,'','SA','Saudi Arabia',966,1),(189,4,'','SN','Senegal',221,1),(190,7,'','RS','Serbia',381,1),(191,4,'','SC','Seychelles',248,1),(192,4,'','SL','Sierra Leone',232,1),(193,1,'','SI','Slovenia',386,1),(194,5,'','SB','Solomon Islands',677,1),(195,4,'','SO','Somalia',252,1),(196,8,'','GS','South Georgia and the South Sandwich Islands',0,1),(197,3,'','LK','Sri Lanka',94,1),(198,4,'','SD','Sudan',249,1),(199,8,'','SR','Suriname',597,1),(200,7,'','SJ','Svalbard and Jan Mayen',0,1),(201,4,'','SZ','Swaziland',268,1),(202,3,'','SY','Syria',963,1),(203,3,'','TW','Taiwan',886,1),(204,3,'','TJ','Tajikistan',992,1),(205,4,'','TZ','Tanzania',255,1),(206,3,'','TH','Thailand',66,1),(207,5,'','TK','Tokelau',690,1),(208,5,'','TO','Tonga',676,1),(209,6,'','TT','Trinidad and Tobago',0,1),(210,4,'','TN','Tunisia',216,1),(211,7,'','TR','Turkey',90,1),(212,3,'','TM','Turkmenistan',993,1),(213,8,'','TC','Turks and Caicos Islands',0,1),(214,5,'','TV','Tuvalu',688,1),(215,4,'','UG','Uganda',256,1),(216,1,'','UA','Ukraine',380,1),(217,3,'','AE','United Arab Emirates',971,1),(218,6,'','UY','Uruguay',598,1),(219,3,'','UZ','Uzbekistan',998,1),(220,5,'','VU','Vanuatu',678,1),(221,6,'','VE','Venezuela',58,1),(222,3,'','VN','Vietnam',84,1),(223,2,'','VG','Virgin Islands (British)',0,1),(224,2,'','VI','Virgin Islands (U.S.)',0,1),(225,5,'','WF','Wallis and Futuna',681,1),(226,4,'','EH','Western Sahara',0,1),(227,3,'','YE','Yemen',967,1),(228,4,'','ZM','Zambia',260,1),(229,4,'','ZW','Zimbabwe',263,1),(230,7,'','AL','Albania',355,1),(231,3,'','AF','Afghanistan',93,1),(232,5,'','AQ','Antarctica',0,1),(233,1,'','BA','Bosnia and Herzegovina',387,1),(234,5,'','BV','Bouvet Island',0,1),(235,5,'','IO','British Indian Ocean Territory',0,1),(236,1,'','BG','Bulgaria',359,1),(237,8,'','KY','Cayman Islands',0,1),(238,3,'','CX','Christmas Island',0,1),(239,3,'','CC','Cocos (Keeling) Islands',0,1),(240,5,'','CK','Cook Islands',682,1),(241,6,'','GF','French Guiana',594,1),(242,5,'','PF','French Polynesia',689,1),(243,5,'','TF','French Southern Territories',0,1),(244,7,'','AX','Åland Islands',0,1)",
    "INSERT INTO `{$_TABLES['shop.states']}` VALUES (1,21,'AA','AA',1),(2,21,'AE','AE',1),(3,21,'AP','AP',1),(4,21,'Alabama','AL',1),(5,21,'Alaska','AK',1),(6,21,'Arizona','AZ',1),(7,21,'Arkansas','AR',1),(8,21,'California','CA',1),(9,21,'Colorado','CO',1),(10,21,'Connecticut','CT',1),(11,21,'Delaware','DE',1),(12,21,'Florida','FL',1),(13,21,'Georgia','GA',1),(14,21,'Hawaii','HI',1),(15,21,'Idaho','ID',1),(16,21,'Illinois','IL',1),(17,21,'Indiana','IN',1),(18,21,'Iowa','IA',1),(19,21,'Kansas','KS',1),(20,21,'Kentucky','KY',1),(21,21,'Louisiana','LA',1),(22,21,'Maine','ME',1),(23,21,'Maryland','MD',1),(24,21,'Massachusetts','MA',1),(25,21,'Michigan','MI',1),(26,21,'Minnesota','MN',1),(27,21,'Mississippi','MS',1),(28,21,'Missouri','MO',1),(29,21,'Montana','MT',1),(30,21,'Nebraska','NE',1),(31,21,'Nevada','NV',1),(32,21,'New Hampshire','NH',1),(33,21,'New Jersey','NJ',1),(34,21,'New Mexico','NM',1),(35,21,'New York','NY',1),(36,21,'North Carolina','NC',1),(37,21,'North Dakota','ND',1),(38,21,'Ohio','OH',1),(39,21,'Oklahoma','OK',1),(40,21,'Oregon','OR',1),(41,21,'Pennsylvania','PA',1),(42,21,'Rhode Island','RI',1),(43,21,'South Carolina','SC',1),(44,21,'South Dakota','SD',1),(45,21,'Tennessee','TN',1),(46,21,'Texas','TX',1),(47,21,'Utah','UT',1),(48,21,'Vermont','VT',1),(49,21,'Virginia','VA',1),(50,21,'Washington','WA',1),(51,21,'West Virginia','WV',1),(52,21,'Wisconsin','WI',1),(53,21,'Wyoming','WY',1),(54,21,'Puerto Rico','PR',1),(55,21,'US Virgin Islands','VI',1),(56,21,'District of Columbia','DC',1),(57,145,'Aguascalientes','AGS',1),(58,145,'Baja California','BCN',1),(59,145,'Baja California Sur','BCS',1),(60,145,'Campeche','CAM',1),(61,145,'Chiapas','CHP',1),(62,145,'Chihuahua','CHH',1),(63,145,'Coahuila','COA',1),(64,145,'Colima','COL',1),(65,145,'Distrito Federal','DIF',1),(66,145,'Durango','DUR',1),(67,145,'Guanajuato','GUA',1),(68,145,'Guerrero','GRO',1),(69,145,'Hidalgo','HID',1),(70,145,'Jalisco','JAL',1),(71,145,'Estado de México','MEX',1),(72,145,'Michoacán','MIC',1),(73,145,'Morelos','MOR',1),(74,145,'Nayarit','NAY',1),(75,145,'Nuevo León','NLE',1),(76,145,'Oaxaca','OAX',1),(77,145,'Puebla','PUE',1),(78,145,'Querétaro','QUE',1),(79,145,'Quintana Roo','ROO',1),(80,145,'San Luis Potosí','SLP',1),(81,145,'Sinaloa','SIN',1),(82,145,'Sonora','SON',1),(83,145,'Tabasco','TAB',1),(84,145,'Tamaulipas','TAM',1),(85,145,'Tlaxcala','TLA',1),(86,145,'Veracruz','VER',1),(87,145,'Yucatán','YUC',1),(88,145,'Zacatecas','ZAC',1),(89,4,'Ontario','ON',1),(90,4,'Quebec','QC',1),(91,4,'British Columbia','BC',1),(92,4,'Alberta','AB',1),(93,4,'Manitoba','MB',1),(94,4,'Saskatchewan','SK',1),(95,4,'Nova Scotia','NS',1),(96,4,'New Brunswick','NB',1),(97,4,'Newfoundland and Labrador','NL',1),(98,4,'Prince Edward Island','PE',1),(99,4,'Northwest Territories','NT',1),(100,4,'Yukon','YT',1),(101,4,'Nunavut','NU',1),(102,44,'Buenos Aires','B',1),(103,44,'Catamarca','K',1),(104,44,'Chaco','H',1),(105,44,'Chubut','U',1),(106,44,'Ciudad de Buenos Aires','C',1),(107,44,'Córdoba','X',1),(108,44,'Corrientes','W',1),(109,44,'Entre Ríos','E',1),(110,44,'Formosa','P',1),(111,44,'Jujuy','Y',1),(112,44,'La Pampa','L',1),(113,44,'La Rioja','F',1),(114,44,'Mendoza','M',1),(115,44,'Misiones','N',1),(116,44,'Neuquén','Q',1),(117,44,'Río Negro','R',1),(118,44,'Salta','A',1),(119,44,'San Juan','J',1),(120,44,'San Luis','D',1),(121,44,'Santa Cruz','Z',1),(122,44,'Santa Fe','S',1),(123,44,'Santiago del Estero','G',1),(124,44,'Tierra del Fuego','V',1),(125,44,'Tucumán','T',1),(126,10,'Agrigento','AG',1),(127,10,'Alessandria','AL',1),(128,10,'Ancona','AN',1),(129,10,'Aosta','AO',1),(130,10,'Arezzo','AR',1),(131,10,'Ascoli Piceno','AP',1),(132,10,'Asti','AT',1),(133,10,'Avellino','AV',1),(134,10,'Bari','BA',1),(135,10,'Barletta-Andria-Trani','BT',1),(136,10,'Belluno','BL',1),(137,10,'Benevento','BN',1),(138,10,'Bergamo','BG',1),(139,10,'Biella','BI',1),(140,10,'Bologna','BO',1),(141,10,'Bolzano','BZ',1),(142,10,'Brescia','BS',1),(143,10,'Brindisi','BR',1),(144,10,'Cagliari','CA',1),(145,10,'Caltanissetta','CL',1),(146,10,'Campobasso','CB',1),(147,10,'Carbonia-Iglesias','CI',1),(148,10,'Caserta','CE',1),(149,10,'Catania','CT',1),(150,10,'Catanzaro','CZ',1),(151,10,'Chieti','CH',1),(152,10,'Como','CO',1),(153,10,'Cosenza','CS',1),(154,10,'Cremona','CR',1),(155,10,'Crotone','KR',1),(156,10,'Cuneo','CN',1),(157,10,'Enna','EN',1),(158,10,'Fermo','FM',1),(159,10,'Ferrara','FE',1),(160,10,'Firenze','FI',1),(161,10,'Foggia','FG',1),(162,10,'Forlì-Cesena','FC',1),(163,10,'Frosinone','FR',1),(164,10,'Genova','GE',1),(165,10,'Gorizia','GO',1),(166,10,'Grosseto','GR',1),(167,10,'Imperia','IM',1),(168,10,'Isernia','IS',1),(169,10,'L\'Aquila','AQ',1),(170,10,'La Spezia','SP',1),(171,10,'Latina','LT',1),(172,10,'Lecce','LE',1),(173,10,'Lecco','LC',1),(174,10,'Livorno','LI',1),(175,10,'Lodi','LO',1),(176,10,'Lucca','LU',1),(177,10,'Macerata','MC',1),(178,10,'Mantova','MN',1),(179,10,'Massa','MS',1),(180,10,'Matera','MT',1),(181,10,'Medio Campidano','VS',1),(182,10,'Messina','ME',1),(183,10,'Milano','MI',1),(184,10,'Modena','MO',1),(185,10,'Monza e della Brianza','MB',1),(186,10,'Napoli','NA',1),(187,10,'Novara','NO',1),(188,10,'Nuoro','NU',1),(189,10,'Ogliastra','OG',1),(190,10,'Olbia-Tempio','OT',1),(191,10,'Oristano','OR',1),(192,10,'Padova','PD',1),(193,10,'Palermo','PA',1),(194,10,'Parma','PR',1),(195,10,'Pavia','PV',1),(196,10,'Perugia','PG',1),(197,10,'Pesaro-Urbino','PU',1),(198,10,'Pescara','PE',1),(199,10,'Piacenza','PC',1),(200,10,'Pisa','PI',1),(201,10,'Pistoia','PT',1),(202,10,'Pordenone','PN',1),(203,10,'Potenza','PZ',1),(204,10,'Prato','PO',1),(205,10,'Ragusa','RG',1),(206,10,'Ravenna','RA',1),(207,10,'Reggio Calabria','RC',1),(208,10,'Reggio Emilia','RE',1),(209,10,'Rieti','RI',1),(210,10,'Rimini','RN',1),(211,10,'Roma','RM',1),(212,10,'Rovigo','RO',1),(213,10,'Salerno','SA',1),(214,10,'Sassari','SS',1),(215,10,'Savona','SV',1),(216,10,'Siena','SI',1),(217,10,'Siracusa','SR',1),(218,10,'Sondrio','SO',1),(219,10,'Taranto','TA',1),(220,10,'Teramo','TE',1),(221,10,'Terni','TR',1),(222,10,'Torino','TO',1),(223,10,'Trapani','TP',1),(224,10,'Trento','TN',1),(225,10,'Treviso','TV',1),(226,10,'Trieste','TS',1),(227,10,'Udine','UD',1),(228,10,'Varese','VA',1),(229,10,'Venezia','VE',1),(230,10,'Verbano-Cusio-Ossola','VB',1),(231,10,'Vercelli','VC',1),(232,10,'Verona','VR',1),(233,10,'Vibo Valentia','VV',1),(234,10,'Vicenza','VI',1),(235,10,'Viterbo','VT',1),(236,111,'Aceh','ID-AC',1),(237,111,'Bali','ID-BA',1),(238,111,'Banten','ID-BT',1),(239,111,'Bengkulu','ID-BE',1),(240,111,'Gorontalo','ID-GO',1),(241,111,'Jakarta','ID-JK',1),(242,111,'Jambi','ID-JA',1),(243,111,'Jawa Barat','ID-JB',1),(244,111,'Jawa Tengah','ID-JT',1),(245,111,'Jawa Timur','ID-JI',1),(246,111,'Kalimantan Barat','ID-KB',1),(247,111,'Kalimantan Selatan','ID-KS',1),(248,111,'Kalimantan Tengah','ID-KT',1),(249,111,'Kalimantan Timur','ID-KI',1),(250,111,'Kalimantan Utara','ID-KU',1),(251,111,'Kepulauan Bangka Belitug','ID-BB',1),(252,111,'Kepulauan Riau','ID-KR',1),(253,111,'Lampung','ID-LA',1),(254,111,'Maluku','ID-MA',1),(255,111,'Maluku Utara','ID-MU',1),(256,111,'Nusa Tengara Barat','ID-NB',1),(257,111,'Nusa Tenggara Timur','ID-NT',1),(258,111,'Papua','ID-PA',1),(259,111,'Papua Barat','ID-PB',1),(260,111,'Riau','ID-RI',1),(261,111,'Sulawesi Barat','ID-SR',1),(262,111,'Sulawesi Selatan','ID-SN',1),(263,111,'Sulawesi Tengah','ID-ST',1),(264,111,'Sulawesi Tenggara','ID-SG',1),(265,111,'Sulawesi Utara','ID-SA',1),(266,111,'Sumatera Barat','ID-SB',1),(267,111,'Sumatera Selatan','ID-SS',1),(268,111,'Sumatera Utara','ID-SU',1),(269,111,'Yogyakarta','ID-YO',1),(270,11,'Aichi','23',1),(271,11,'Akita','05',1),(272,11,'Aomori','02',1),(273,11,'Chiba','12',1),(274,11,'Ehime','38',1),(275,11,'Fukui','18',1),(276,11,'Fukuoka','40',1),(277,11,'Fukushima','07',1),(278,11,'Gifu','21',1),(279,11,'Gunma','10',1),(280,11,'Hiroshima','34',1),(281,11,'Hokkaido','01',1),(282,11,'Hyogo','28',1),(283,11,'Ibaraki','08',1),(284,11,'Ishikawa','17',1),(285,11,'Iwate','03',1),(286,11,'Kagawa','37',1),(287,11,'Kagoshima','46',1),(288,11,'Kanagawa','14',1),(289,11,'Kochi','39',1),(290,11,'Kumamoto','43',1),(291,11,'Kyoto','26',1),(292,11,'Mie','24',1),(293,11,'Miyagi','04',1),(294,11,'Miyazaki','45',1),(295,11,'Nagano','20',1),(296,11,'Nagasaki','42',1),(297,11,'Nara','29',1),(298,11,'Niigata','15',1),(299,11,'Oita','44',1),(300,11,'Okayama','33',1),(301,11,'Okinawa','47',1),(302,11,'Osaka','27',1),(303,11,'Saga','41',1),(304,11,'Saitama','11',1),(305,11,'Shiga','25',1),(306,11,'Shimane','32',1),(307,11,'Shizuoka','22',1),(308,11,'Tochigi','09',1),(309,11,'Tokushima','36',1),(310,11,'Tokyo','13',1),(311,11,'Tottori','31',1),(312,11,'Toyama','16',1),(313,11,'Wakayama','30',1),(314,11,'Yamagata','06',1),(315,11,'Yamaguchi','35',1),(316,11,'Yamanashi','19',1),(317,24,'Australian Capital Territory','ACT',1),(318,24,'New South Wales','NSW',1),(319,24,'Northern Territory','NT',1),(320,24,'Queensland','QLD',1),(321,24,'South Australia','SA',1),(322,24,'Tasmania','TAS',1),(323,24,'Victoria','VIC',1),(324,24,'Western Australia','WA',1)",
);

$SHOP_UPGRADE['0.7.1'] = array(
    "ALTER TABLE {$_TABLES['shop.shipping']} ADD `valid_from` int(11) unsigned NOT NULL DEFAULT '0' AFTER `enabled`",
    "ALTER TABLE {$_TABLES['shop.shipping']} ADD `valid_to` int(11) unsigned NOT NULL DEFAULT '2145902399' AFTER `valid_from`",
    "ALTER TABLE {$_TABLES['shop.shipping']} ADD `use_fixed` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `valid_to`",
    "ALTER TABLE {$_TABLES['shop.orderitems']} DROP `status`",
    "ALTER TABLE {$_TABLES['shop.ipnlog']} ADD order_id varchar(40)",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `shipper_id` int(3) UNSIGNED DEFAULT '0' AFTER `order_seq`",
);
$SHOP_UPGRADE['1.0.0'] = array(
    "CREATE TABLE `{$_TABLES['shop.prod_opt_grps']}` (
      `pog_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `pog_type` varchar(11) NOT NULL DEFAULT 'select',
      `pog_name` varchar(40) NOT NULL DEFAULT '',
      `pog_orderby` tinyint(2) DEFAULT '0',
      PRIMARY KEY (`pog_id`),
      UNIQUE KEY `pog_name` (`pog_name`),
      KEY `orderby` (`pog_orderby`,`pog_name`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.oi_opts']}` (
      `oio_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `oi_id` int(11) unsigned NOT NULL,
      `pog_id` int(11) unsigned NOT NULL DEFAULT '0',
      `pov_id` int(11) unsigned NOT NULL DEFAULT '0',
      `oio_name` varchar(40) DEFAULT NULL,
      `oio_value` varchar(40) DEFAULT NULL,
      `oio_price` decimal(9,4) NOT NULL DEFAULT '0.0000',
      PRIMARY KEY (`oio_id`),
      UNIQUE KEY `key1` (`oi_id`,`pog_id`,`pov_id`,`oio_name`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.shipments']}` (
      `shipment_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `order_id` varchar(40) DEFAULT NULL,
      `ts` int(11) unsigned DEFAULT NULL,
      `comment` text,
      `shipping_address` text,
      PRIMARY KEY (`shipment_id`),
      KEY `order_id` (`order_id`,`ts`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.shipment_items']}` (
      `si_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `shipment_id` int(11) unsigned NOT NULL DEFAULT '0',
      `orderitem_id` int(11) unsigned NOT NULL DEFAULT '0',
      `quantity` int(11) NOT NULL DEFAULT '0',
      PRIMARY KEY (`si_id`),
      KEY `shipment_id` (`shipment_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.shipment_packages']}` (
      `pkg_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `shipment_id` int(11) unsigned NOT NULL DEFAULT '0',
      `shipper_id` int(11) unsigned NOT NULL DEFAULT '0',
      `shipper_info` varchar(255) DEFAULT NULL,
      `tracking_num` varchar(80) DEFAULT NULL,
      PRIMARY KEY (`pkg_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE {$_TABLES['shop.carrier_config']} (
      `code` varchar(10) NOT NULL,
      `data` text,
      PRIMARY KEY (`code`)
    ) ENGINE=MyISAM",
    "CREATE TABLE `{$_TABLES['shop.cache']}` (
      `cache_key` varchar(127) NOT NULL,
      `expires` int(11) unsigned NOT NULL DEFAULT '0',
      `data` mediumtext,
      PRIMARY KEY (`cache_key`),
      KEY (`expires`)
    ) ENGINE=MyISAM",
    "ALTER TABLE {$_TABLES['shop.sales']} CHANGE `start` `start` datetime NOT NULL DEFAULT '1970-01-01 00:00:00'",
    "ALTER TABLE {$_TABLES['shop.address']} CHANGE id addr_id int(11) unsigned NOT NULL auto_increment",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `brand` varchar(255) NOT NULL DEFAULT ''",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `min_ord_qty` int(3) NOT NULL DEFAULT 1",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `max_ord_qty` int(3) NOT NULL DEFAULT 0",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `brand_id` int(11) NOT NULL DEFAULT 0",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `supplier_id` int(11) NOT NULL DEFAULT 0",
    // Note: Removal of the products `brand` field happens in upgrade.php after brand_id is populated
    "ALTER TABLE {$_TABLES['shop.shipping']} ADD `grp_access` int(3) UNSIGNED NOT NULL default 2",
    "ALTER TABLE {$_TABLES['shop.shipping']} ADD `module_code` varchar(10) AFTER `id`",
    "ALTER TABLE {$_TABLES['shop.orderitems']} CHANGE  price price  decimal(9,4) NOT NULL default  0",
    "ALTER TABLE {$_TABLES['shop.orderitems']} ADD base_price decimal(9,4) NOT NULL default 0 AFTER expiration",
    "ALTER TABLE {$_TABLES['shop.orderitems']} ADD qty_discount decimal(5,2) NOT NULL default 0 AFTER price",
    "ALTER TABLE {$_TABLES['shop.categories']} ADD google_taxonomy text AFTER `image`",
    "ALTER TABLE {$_TABLES['shop.images']} ADD `nonce` varchar(20) DEFAULT NULL",
    "ALTER TABLE {$_TABLES['shop.images']} ADD `last_update` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    "RENAME TABLE {$_TABLES['shop.prod_attr']} TO {$_TABLES['shop.prod_opt_vals']}",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} CHANGE attr_id pov_id int(11) unsigned NOT NULL AUTO_INCREMENT",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} CHANGE attr_value pov_value varchar(64) DEFAULT NULL",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} CHANGE attr_price pov_price decimal(9,4) DEFAULT NULL",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} ADD `pog_id` int(11) UNSIGNED NOT NULL AFTER `pov_id`",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} ADD `sku` varchar(8) DEFAUlt NULL",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP KEY IF EXISTS `item_id`",
    "ALTER TABLE {$_TABLES['shop.coupons']} DROP PRIMARY KEY",
    "ALTER TABLE {$_TABLES['shop.coupons']} ADD UNIQUE KEY `code` (`code`)",
    "ALTER TABLE {$_TABLES['shop.coupons']} ADD `id` int(11) unsigned NOT NULL auto_increment PRIMARY KEY FIRST",
    "ALTER TABLE {$_TABLES['shop.coupons']} ADD `status` varchar(10) NOT NULL DEFAULT 'valid'",
    "ALTER TABLE {$_TABLES['shop.gateways']} ADD `grp_access` int(3) UNSIGNED NOT NULL default 2",
    "ALTER TABLE {$_TABLES['shop.images']} ADD `orderby` int(3) NOT NULL default 999 AFTER `product_id`",
);

$SHOP_UPGRADE['1.1.0'] = array(
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.tax_rates']}` (
      `code` varchar(25) NOT NULL,
      `country` varchar(3) DEFAULT NULL,
      `state` varchar(10) DEFAULT NULL,
      `zip_from` varchar(10) DEFAULT NULL,
      `zip_to` varchar(10) DEFAULT NULL,
      `region` varchar(40) DEFAULT NULL,
      `combined_rate` float(7,5) NOT NULL DEFAULT '0.00000',
      `state_rate` float(7,5) NOT NULL DEFAULT '0.00000',
      `county_rate` float(7,5) NOT NULL DEFAULT '0.00000',
      `city_rate` float(7,5) NOT NULL DEFAULT '0.00000',
      `special_rate` float(7,5) NOT NULL DEFAULT '0.00000',
      PRIMARY KEY (`code`),
      KEY `country_zipcode` (`country`,`zip_from`),
      KEY `location` (`country`,`state`,`zip_from`),
      KEY `zip_from` (`zip_from`),
      KEY `zip_to` (`zip_to`)
    ) ENGINE=MyISAM",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.discountcodes']}` (
      `code_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `code` varchar(80) NOT NULL DEFAULT '',
      `percent` decimal(4,2) unsigned NOT NULL DEFAULT '0.00',
      `start` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
      `end` datetime NOT NULL DEFAULT '9999-12-31 23:59:59',
          `min_order` decimal(9,4) unsigned NOT NULL DEFAULT '0.0000',
      PRIMARY KEY (`code_id`),
      UNIQUE KEY `code` (`code`),
      KEY `bydate` (`start`,`end`)
    ) ENGINE=MyISAM",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.prodXcat']}` (
      `product_id` int(11) unsigned NOT NULL,
      `cat_id` int(11) unsigned NOT NULL,
      PRIMARY KEY (`product_id`,`cat_id`),
      KEY `cat_id` (`cat_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.product_variants']}` (
      `pv_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `item_id` int(11) unsigned NOT NULL,
      `sku` varchar(64) DEFAULT NULL,
      `price` decimal(9,4) NOT NULL DEFAULT '0.0000',
      `weight` decimal(12,4) NOT NULL DEFAULT '0.0000',
      `shipping_units` decimal(9,4) NOT NULL DEFAULT '0.0000',
      `onhand` int(10) NOT NULL DEFAULT '0',
      `reorder` int(10) NOT NULL DEFAULT '0',
      `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
      PRIMARY KEY (`pv_id`),
      KEY `prod_id` (`item_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.variantXopt']}` (
      `pv_id` int(11) unsigned NOT NULL DEFAULT '0',
      `pov_id` int(11) unsigned NOT NULL DEFAULT '0',
      PRIMARY KEY (`pv_id`,`pov_id`)
    ) ENGINE=MyISAM",
    "CREATE TABLE IF NOT EXISTS `{$_TABLES['shop.suppliers']}` (
      `sup_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `name` varchar(127) DEFAULT NULL,
      `company` varchar(127) NOT NULL DEFAULT '',
      `address1` varchar(127) NOT NULL DEFAULT '',
      `address2` varchar(127) NOT NULL DEFAULT '',
      `city` varchar(127) NOT NULL DEFAULT '',
      `state` varchar(127) NOT NULL DEFAULT '',
      `country` varchar(127) NOT NULL DEFAULT '',
      `zip` varchar(40) NOT NULL DEFAULT '',
      `phone` varchar(40) NOT NULL DEFAULT '',
      `is_supplier` tinyint(1) unsigned NOT NULL DEFAULT '1',
      `is_brand` tinyint(1) unsigned NOT NULL DEFAULT '0',
      `dscp` text,
      PRIMARY KEY (`sup_id`),
      KEY `is_supplier` (`is_supplier`,`name`),
      KEY `is_brand` (`is_brand`,`name`)
    ) ENGINE=MyISAM",
    "ALTER TABLE {$_TABLES['shop.address']} ADD phone varchar(20) AFTER zip",
    "ALTER TABLE {$_TABLES['shop.userinfo']} ADD `pref_gw` varchar(12) NOT NULL DEFAULT ''",
    "ALTER TABLE {$_TABLES['shop.orderitems']} ADD dc_price decimal(9,4) NOT NULL DEFAULT 0 after qty_discount",
    "ALTER TABLE {$_TABLES['shop.orderitems']} ADD `variant_id` int(11) unsigned NOT NULL DEFAULT '0' AFTER product_id",
    "ALTER TABLE {$_TABLES['shop.orderitems']} ADD `net_price` decimal(9,4) NOT NULL DEFAULT '0.0000' AFTER qty_discount",
    "ALTER TABLE {$_TABLES['shop.orderitems']} ADD `tax_rate` decimal(6,4) NOT NULL DEFAULT  '0.0000' AFTER `tax`",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `gross_items` decimal(12,4) NOT NULL DEFAULT '0.0000' AFTER buyer_email",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `net_nontax` decimal(12,4) NOT NULL DEFAULT '0.0000' AFTER gross_items",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `net_taxable` decimal(12,4) NOT NULL DEFAULT '0.0000' AFTER net_nontax",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `order_total` decimal(12,4) unsigned DEFAULT '0.0000' AFTER net_taxable",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `discount_code` varchar(20) DEFAULT NULL AFTER shipper_id",
    "ALTER TABLE {$_TABLES['shop.orders']} ADD `discount_pct` decimal(4,2) DEFAULT '0.00' AFTER discount_code",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP KEY `item_id`",
    "ALTER TABLE {$_TABLES['shop.prod_opt_vals']} DROP `item_id`",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `brand_id` int(11) unsigned NOT NULL DEFAULT 0 AFTER `max_ord_qty`",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `supplier_id` int(11) unsigned NOT NULL DEFAULT 0 AFTER `brand_id`",
    "ALTER TABLE {$_TABLES['shop.products']} ADD `reorder` int(10) unsigned NOT NULL DEFAULT 0 after `onhand`",
);
$SHOP_UPGRADE['1.2.0'] = array(
    $_SQL['shop.regions'],
    $_SQL['shop.countries'],
    $_SQL['shop.states'],
    $_SHOP_SAMPLEDATA[5],
    $_SHOP_SAMPLEDATA[6],
    $_SHOP_SAMPLEDATA[7],
);

$_SQL['shop.prod_opt_grps'] = $SHOP_UPGRADE['1.0.0'][0];
$_SQL['shop.oi_opts'] = $SHOP_UPGRADE['1.0.0'][1];
$_SQL['shop.shipments'] = $SHOP_UPGRADE['1.0.0'][2];
$_SQL['shop.shipment_items'] = $SHOP_UPGRADE['1.0.0'][3];
$_SQL['shop.shipment_packages'] = $SHOP_UPGRADE['1.0.0'][4];
$_SQL['shop.carrier_config'] = $SHOP_UPGRADE['1.0.0'][5];
$_SQL['shop.cache'] = $SHOP_UPGRADE['1.0.0'][6];
$_SQL['shop.tax_rates'] = $SHOP_UPGRADE['1.1.0'][0];
$_SQL['shop.discountcodes'] = $SHOP_UPGRADE['1.1.0'][1];
$_SQL['shop.prodXcat'] = $SHOP_UPGRADE['1.1.0'][2];
$_SQL['shop.product_variants'] = $SHOP_UPGRADE['1.1.0'][3];
$_SQL['shop.variantXopt'] = $SHOP_UPGRADE['1.1.0'][4];
$_SQL['shop.suppliers'] = $SHOP_UPGRADE['1.1.0'][5];

?>
