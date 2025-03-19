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

defined('JPATH_BASE') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldmapTypeAvailable extends ListField
{
    protected $type = 'mapTypeAvailable';

    protected function getOptions()
    {
        $ar = array();
        $ar['ROADMAP']   = Text::_('COM_GEOFACTORY_MAP_NORMAL');
        $ar['SATELLITE'] = Text::_('COM_GEOFACTORY_MAP_SATELLITE');
        $ar['HYBRID']    = Text::_('COM_GEOFACTORY_MAP_HYBRID');
        $ar['TERRAIN']   = Text::_('COM_GEOFACTORY_MAP_PHYSICAL');

        $paramsMapTypes = $this->form->getValue('params_map_types');

        if (empty($paramsMapTypes)) {
            return $ar;
        }
        
        $tilesDb = (string)$paramsMapTypes->tiles;
        $listTile = explode(";", $tilesDb);

        if (count($listTile) < 1) {
            return $ar;
        }
        
        foreach ($listTile as $tile) {
            $tile = explode('|', $tile);
            $name = (count($tile) > 1) ? trim($tile[1]) : null;
            if (!$name) {
                continue;
            }
            $ar[$name] = $name;
        }
        
        return $ar;
    }

    protected function getInput()
    {
        $html = array();
        $html[] = parent::getInput();
        return implode('', $html);
    }
}