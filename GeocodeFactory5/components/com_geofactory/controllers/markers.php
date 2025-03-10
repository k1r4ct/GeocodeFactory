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
require_once JPATH_COMPONENT . '/helpers/geofactory.php';

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Component\ComponentHelper;

class GeofactoryControllerMarkers extends BaseController
{
    protected $text_prefix = 'COM_GEOFACTORY';

    public function getJson()
    {
        $app   = Factory::getApplication();
        $idMap = $app->input->getInt('idmap', -1);
        $model = $this->getModel('Markers');
        $json  = $model->createfile($idMap, 'json');
        $config = ComponentHelper::getParams('com_geofactory');
        $mem   = $config->get('largeMarkers', 64);

        // Prepara la memoria
        ini_set("memory_limit", $mem . "M");
        if ((int)$mem > 128) {
            set_time_limit(0);
        }

        @ob_clean();
        flush();
        echo $json;
        exit;
    }

    public function dyncat()
    {
        $app   = Factory::getApplication();
        $idMap = $app->input->getInt('idmap', -1);
        $model = $this->getModel('Markers');

        $idP    = $app->input->getInt('idP', 0);
        $ext    = $app->input->getString('ext', '');
        $mapVar = $app->input->getString('mapVar', '');
        $select = $model->getCategorySelect($ext, $idP, $mapVar);

        @ob_clean();
        flush();
        echo $select;
        exit;
    }
}
