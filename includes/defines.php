<?php
/**
 * @package    Cobalt.CRM
 *
 * @copyright  Copyright (C) 2012 Cobalt. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Cobalt Application define.
 */

//Global definitions.
//Joomla framework path definitions.
$parts = explode(DIRECTORY_SEPARATOR, JPATH_BASE);

//Defines.
define('JPATH_ROOT',          implode(DIRECTORY_SEPARATOR, $parts));

define('JPATH_SITE',          JPATH_ROOT);
define('JPATH_COBALT',		  JPATH_ROOT . '/libraries/crm');
define('JPATH_CONFIGURATION', JPATH_ROOT);
define('JPATH_ADMINISTRATOR', JPATH_ROOT . '/admin');
define('JPATH_LIBRARIES',     JPATH_ROOT . '/libraries');
define('JPATH_PLUGINS',       JPATH_ROOT . '/plugins');
define('JPATH_INSTALLATION',  JPATH_ROOT . '/install');
define('JPATH_THEMES',        JPATH_BASE . '/themes');
define('JPATH_CACHE',         JPATH_BASE . '/cache');
define('JROUTER_MODE_SEF', 	  1);