<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;

// Non è più necessario caricare la classe con JFormHelper::loadFieldClass('list')
// Includo il file helper del backend
require_once JPATH_SITE . '/administrator/components/com_geofactory/helpers/geofactory.php';
// Includo il file del campo "listmaps" definito nel backend
require_once JPATH_SITE . '/administrator/components/com_geofactory/models/fields/listmaps.php';
