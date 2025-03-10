<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update		Daniele Bellante
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;

class GeofactoryModelMaploader extends ItemModel
{
    public function getItem($pk = null)
    {
        $app = Factory::getApplication('site');
        // Recupera l'idMap passato via JS (non dal menu Joomla)
        $idMap = $app->input->getInt('idMap', null);

        $map = GeofactoryGgmapHelper::getMap($idMap);
        $ms  = GeofactoryGgmapHelper::getArrayIdMs($idMap);
        // Il metodo _createDataFile deve essere implementato in base alla logica specifica
        $this->_createDataFile($out, $idsMs, $map);

        $app->close();
    }
}
