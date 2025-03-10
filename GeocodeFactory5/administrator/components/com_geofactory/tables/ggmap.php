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

use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Filter\OutputFilter;

class GeofactoryTableGgmap extends Table
{
    public function __construct(&$db)
    {
        $this->checked_out_time = $db->getNullDate();
        parent::__construct('#__geofactory_ggmaps', 'id', $db);
    }
    
    public function bind($array, $ignore = '')
    {
        if (isset($array['params_map_cluster']) && is_array($array['params_map_cluster'])) {
            $registry = new Registry;
            $registry->loadArray($array['params_map_cluster']);
            $array['params_map_cluster'] = (string) $registry;
        }
        if (isset($array['params_map_radius']) && is_array($array['params_map_radius'])) {
            $registry = new Registry;
            $registry->loadArray($array['params_map_radius']);
            $array['params_map_radius'] = (string) $registry;
        }
        if (isset($array['params_additional_data']) && is_array($array['params_additional_data'])) {
            $registry = new Registry;
            $registry->loadArray($array['params_additional_data']);
            $array['params_additional_data'] = (string) $registry;
        }
        if (isset($array['params_map_types']) && is_array($array['params_map_types'])) {
            $registry = new Registry;
            $registry->loadArray($array['params_map_types']);
            $array['params_map_types'] = (string) $registry;
        }
        if (isset($array['params_map_controls']) && is_array($array['params_map_controls'])) {
            $registry = new Registry;
            $registry->loadArray($array['params_map_controls']);
            $array['params_map_controls'] = (string) $registry;
        }
        if (isset($array['params_map_settings']) && is_array($array['params_map_settings'])) {
            $registry = new Registry;
            $registry->loadArray($array['params_map_settings']);
            $array['params_map_settings'] = (string) $registry;
        }
        if (isset($array['params_map_mouse']) && is_array($array['params_map_mouse'])) {
            $registry = new Registry;
            $registry->loadArray($array['params_map_mouse']);
            $array['params_map_mouse'] = (string) $registry;
        }
        if (isset($array['params_extra']) && is_array($array['params_extra'])) {
            $registry = new Registry;
            $registry->loadArray($array['params_extra']);
            $array['params_extra'] = (string) $registry;
        }
        return parent::bind($array, $ignore);
    }
    
    public function check()
    {
        $this->name = htmlspecialchars_decode($this->name, ENT_QUOTES);
        $this->alias = OutputFilter::stringURLSafe($this->alias);
        if (empty($this->alias)) {
            $this->alias = OutputFilter::stringURLSafe($this->name);
        }
        return true;
    }
    
    public function importFromOldGF($old)
    {
        // Base data
        $this->name = $old->title;
        $this->template = $old->introduction;
        $this->template = str_replace('[', '{', $this->template);
        $this->template = str_replace(']', '}', $this->template);
        $this->template = str_replace('&lt;', '<', $this->template);
        $this->template = str_replace('&gt;', '>', $this->template);
        $this->template = str_replace('&quot;', '"', $this->template);
        $this->extrainfo = "Backend map description...";
        $this->alias = '';
        $this->mapwidth = "width: " . $old->width . ' ' . $old->unitW;
        $this->mapheight = "height: " . $old->height . ' ' . $old->unitH;
        $this->totalmarkers = $old->totalmarkers;
        $this->centerlat = $old->centerlat;
        $this->centerlng = $old->centerlong;
        $this->state = $old->public;
        
        $params_map_cluster = array(
            'useCluster' => $old->useCluster,
            'clusterZoom' => $old->clusterZoom,
            'gridSize' => $old->gridSize,
            'imagePath' => $old->imagePath,
            'imageSizes' => $old->imageSizes,
            'minimumClusterSize' => $old->minimumClusterSize
        );
        
        $params_map_radius = array(
            'drawCircle' => $old->drawCircle,
            'frontDistSelect' => $old->frontDistSelect,
            'fe_rad_unit' => $old->fe_rad_unit,
            'acCountry' => $old->acCountry,
            'useBrowserRadLoad' => $old->useBrowserRadLoad,
            'acTypes' => $old->acTypes
        );
        
        $params_additional_data = array(
            'kml_file' => $old->kml_file,
            'layers' => $old->layers
        );
        
        $params_map_types = array(
            'mapControl' => $old->mapControl,
            'mapTypeBar' => $old->mapTypeBar,
            'mapTypeAvailable' => $old->mapTypeAvailable,
            'mapTypeOnStart' => $old->mapTypeOnStart,
            'tiles' => $old->tiles
        );
        
        $params_map_controls = array(
            'mapsZoom' => $old->mapsZoom,
            'centerUser' => $old->centerUser,
            'minZoom' => $old->minZoom,
            'maxZoom' => $old->maxZoom,
            'mapTypeControl' => $old->mapTypeControl,
            'pegman' => $old->pegman,
            'scaleControl' => $old->scaleControl,
            'rotateControl' => $old->rotateControl,
            'overviewMapControl' => $old->overviewMapControl
        );
        
        $params_map_settings = array(
            'allowDbl' => $old->allowDbl,
            'randomMarkers' => $old->randomMarkers,
            'useRoutePlaner' => $old->useRoutePlaner,
            'cacheTime' => $old->cacheTime,
            'mapStyle' => $old->mapStyle
        );
        
        $params_map_mouse = array(
            'doubleClickZoom' => $old->doubleClickZoom,
            'wheelZoom' => $old->wheelZoom,
            'bubbleOnOver' => $old->bubbleOnOver,
            'clickRadius' => $old->clickRadius,
            'salesRadMode' => $old->salesRadMode,
            'trackOnOver' => $old->trackOnOver,
            'trackZoom' => $old->trackZoom
        );
        
        $registry = new Registry;
        $registry->loadArray($params_map_cluster);
        $this->params_map_cluster = (string) $registry;
        
        $registry = new Registry;
        $registry->loadArray($params_map_radius);
        $this->params_map_radius = (string) $registry;
        
        $registry = new Registry;
        $registry->loadArray($params_additional_data);
        $this->params_additional_data = (string) $registry;
        
        $registry = new Registry;
        $registry->loadArray($params_map_types);
        $this->params_map_types = (string) $registry;
        
        $registry = new Registry;
        $registry->loadArray($params_map_controls);
        $this->params_map_controls = (string) $registry;
        
        $registry = new Registry;
        $registry->loadArray($params_map_settings);
        $this->params_map_settings = (string) $registry;
        
        $registry = new Registry;
        $registry->loadArray($params_map_mouse);
        $this->params_map_mouse = (string) $registry;
    }
    
    public function publish($pks = null, $state = 1, $userId = 0)
    {
        $k = $this->_tbl_key;
        ArrayHelper::toInteger($pks);
        $userId = (int) $userId;
        $state  = (int) $state;
        
        if (empty($pks))
        {
            if ($this->$k)
            {
                $pks = array($this->$k);
            }
            else {
                $this->setError(Text::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED'));
                return false;
            }
        }
        
        $where = $k . '=' . implode(' OR ' . $k . '=', $pks);
        
        if (!property_exists($this, 'checked_out') || !property_exists($this, 'checked_out_time'))
        {
            $checkin = '';
        }
        else
        {
            $checkin = ' AND (checked_out = 0 OR checked_out = ' . (int) $userId . ')';
        }
        
        $db = $this->_db;
        $db->setQuery(
            'UPDATE ' . $db->quoteName($this->_tbl) .
            ' SET ' . $db->quoteName('state') . ' = ' . (int) $state .
            ' WHERE (' . $where . ')' . $checkin
        );
        
        try {
            $db->execute();
        }
        catch (\RuntimeException $e) {
            $this->setError($e->getMessage());
            return false;
        }
        
        if ($checkin && (count($pks) == $db->getAffectedRows()))
        {
            foreach ($pks as $pk)
            {
                $this->checkin($pk);
            }
        }
        
        if (in_array($this->$k, $pks))
        {
            $this->state = $state;
        }
        
        $this->setError('');
        return true;
    }
}
