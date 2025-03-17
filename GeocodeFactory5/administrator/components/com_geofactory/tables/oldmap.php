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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;

// Includiamo direttamente helper invece di usare Loader::register
require_once JPATH_COMPONENT . '/helpers/geofactory.php';

class GeofactoryTableOldmap extends Table
{
    public function __construct($db = null)
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $extDb  = $config->get('import-database');
        
        // Se è configurato un database esterno, lo carichiamo
        if (!empty($extDb)) { 
            $db = GeofactoryHelperAdm::loadExternalDb();
        }

        parent::__construct('#__geocode_factory_maps', 'id', $db);
    }

    public function load($pk = null, $reset = true)
    {
        // Carica l'oggetto di base
        $result = parent::load($pk, $reset);
        
        if (!$result) {
            return false;
        }
        
        // Carica i parametri multi dell'antico GF
        $listVar = array(
            'kml_file'            => '',
            'wheelZoom'           => 0,
            'mapTypeBar'          => "DEFAULT",
            'drawCircle'          => 1,
            'frontDistSelect'     => '10,25,50,100,500',
            'fe_rad_unit'         => 0,
            'clickRadius'         => 1,
            'cacheTime'           => 0,
            'showFriends'         => 1,
            'useTabs'             => 0,
            'allowDbl'            => 0,
            'useGallery'          => 0,
            'tiles'               => '',
            'bubbleOnOver'        => 0,
            'lineMyAddresses'     => 0,
            'useRoutePlaner'      => 0,
            'useBrowserRadLoad'   => 0,
            'mapTypeAvailable'    => "SATELLITE,HYBRID,TERRAIN,ROADMAP",
            'pegman'              => 1,
            'scaleControl'        => 1,
            'rotateControl'       => 1,
            'overviewMapControl'  => 1,
            'gridSize'            => '',
            'imagePath'           => '',
            'imageSizes'          => '',
            'minimumClusterSize'  => '',
            'randomMarkers'       => 1,
            'layers'              => "",
            'trackOnOver'         => 0,
            'trackZoom'           => 0,
            'mapStyle'            => '',
            'salesRadMode'        => 0,
            'acCountry'           => '',
            'acTypes'             => 0
        );
        
        GeofactoryHelperAdm::loadMultiParamFor($listVar, 1, $pk, $this);
        
        return $result;
    }
}