<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

require_once JPATH_COMPONENT . '/helpers/geofactory.php';

// Se esiste il file gus.php (relativo al componente), lo includiamo.
// Nota: si usa JPATH_COMPONENT per garantire il percorso assoluto.
if (file_exists(JPATH_COMPONENT . '/components/gus.php')) {
    include JPATH_COMPONENT . '/components/gus.php';
}

class GeofactoryController extends BaseController
{
    public function gus()
    {
        // Utilizza Factory::getDbo() per ottenere l'oggetto DB in Joomla 4
        if (function_exists('_gus')) {
            _gus(Factory::getDbo());
        } else {
            echo "not available here!";
        }
    }
}