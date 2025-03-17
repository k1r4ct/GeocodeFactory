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

class GeofactoryTableOldmarkerset extends Table
{
    public function __construct($db = null)
    {
        $config = ComponentHelper::getParams('com_geofactory');
        $extDb  = $config->get('import-database');
        
        // Se è configurato un database esterno, lo carichiamo
        if (!empty($extDb)) { 
            $db = GeofactoryHelperAdm::loadExternalDb();
        }

        parent::__construct('#__geocode_factory_markersets', 'id', $db);
    }

    public function load($pk = null, $reset = true)
    {
        // Carica l'oggetto di base
        $result = parent::load($pk, $reset);
        
        if (!$result) {
            return false;
        }

        // Carica i parametri multi in funzione del markerset
        $listVar = array();
        
        if ($this->typeList == "MS_CB") {
            $listVar = array(
                'sidebar_template' => '',
                'markerIconType'   => 99,
                'j_menu_id'        => 0,
                'bubblewidth'      => '',
                'mapicon'          => '',
                'avatarAsIcon'     => 0,
                'avatarSizeH'      => '',
                'avatarSizeW'      => '',
                'salesRadField'    => 0,
                'onlyOnline'       => 0,
                'onlineTmp'        => '<span style="color:green; font-weight:bold;">ONLINE</span>',
                'offlineTmp'       => '<span style="color:red; font-weight:bold;">OFFLINE</span>',
                'field_spec_lat'   => 0,
                'field_spec_lng'   => 0
            );
        }
        else if ($this->typeList == "MS_JS") {
            $listVar = array(
                'sidebar_template' => '',
                'markerIconType'   => 99,
                'j_menu_id'        => 0,
                'bubblewidth'      => '',
                'mapicon'          => '',
                'avatarAsIcon'     => 0,
                'avatarSizeH'      => '',
                'avatarSizeW'      => '',
                'salesRadField'    => 0,
                'onlyOnline'       => 0,
                'onlineTmp'        => '<span style="color:green; font-weight:bold;">ONLINE</span>',
                'offlineTmp'       => '<span style="color:red; font-weight:bold;">OFFLINE</span>',
                'field_spec_lat'   => 0,
                'field_spec_lng'   => 0
            );
        }
        else if ($this->typeList == "MS_S2") {
            $listVar = array(
                'sidebar_template' => '',
                'markerIconType'   => 99,
                'j_menu_id'        => 0,
                'bubblewidth'      => '',
                'mapicon'          => '',
                'avatarAsIcon'     => 0,
                'avatarSizeH'      => '',
                'avatarSizeW'      => '',
                'salesRadField'    => 0,
                'pline'            => 0,
                'catAuto'          => 0,
                'field_spec_lat'   => 0,
                'field_spec_lng'   => 0,
                'categoryAsIcon'   => 0
            );
        }
        else if ($this->typeList == "MS_MT") {
            $listVar = array(
                'sidebar_template' => '',
                'markerIconType'   => 99,
                'j_menu_id'        => 0,
                'bubblewidth'      => '',
                'mapicon'          => '',
                'avatarAsIcon'     => 0,
                'avatarSizeH'      => '',
                'avatarSizeW'      => '',
                'salesRadField'    => 0,
                'pline'            => 0,
                'catAuto'          => 0,
                'categoryAsIcon'   => 0
            );
        }
        else if ($this->typeList == "MS_JSEV") {
            $listVar = array(
                'sidebar_template' => '',
                'markerIconType'   => 99,
                'j_menu_id'        => 0,
                'bubblewidth'      => '',
                'mapicon'          => '',
                'avatarAsIcon'     => 0,
                'avatarSizeH'      => '',
                'avatarSizeW'      => '',
                'pline'            => 0
            );
        }
        else if ($this->typeList == "MS_SP") {
            $listVar = array(
                'sidebar_template' => '',
                'markerIconType'   => 99,
                'j_menu_id'        => 0,
                'bubblewidth'      => '',
                'mapicon'          => '',
                'avatarAsIcon'     => 0,
                'avatarSizeH'      => '',
                'avatarSizeW'      => '',
                'salesRadField'    => 0,
                'pline'            => 0,
                'catAuto'          => 0,
                'field_spec_lat'   => 0,
                'field_spec_lng'   => 0,
                'section'          => 0,
                'avatarImage'      => 0,
                'filter_opt'       => '',
                'categoryAsIcon'   => 0
            );
        }
        else if ($this->typeList == "MS_AM") {
            $listVar = array(
                'sidebar_template' => '',
                'markerIconType'   => 99,
                'j_menu_id'        => 0,
                'bubblewidth'      => '',
                'mapicon'          => '',
                'avatarAsIcon'     => 0,
                'avatarSizeH'      => '',
                'avatarSizeW'      => '',
                'salesRadField'    => 0,
                'pline'            => 0,
                'catAuto'          => 0
            );
        }
        else if ($this->typeList == "MS_JEV") {
            $listVar = array(
                'sidebar_template' => '',
                'markerIconType'   => 99,
                'j_menu_id'        => 0,
                'bubblewidth'      => '',
                'mapicon'          => '',
                'avatarAsIcon'     => 0,
                'avatarSizeH'      => '',
                'avatarSizeW'      => '',
                'pline'            => 0,
                'dateFormat'       => "d-m-Y",
                'allEvents'        => 0
            );
        }
        else if ($this->typeList == "MS_JC") {
            $listVar = array(
                'sidebar_template' => '',
                'markerIconType'   => 99,
                'j_menu_id'        => 0,
                'bubblewidth'      => '',
                'mapicon'          => '',
                'avatarAsIcon'     => 0,
                'avatarSizeH'      => '',
                'avatarSizeW'      => '',
                'pline'            => 0,
                'catAuto'          => 0
            );
        }
        else if ($this->typeList == "MS_GT") {
            $listVar = array(
                'sidebar_template' => '',
                'markerIconType'   => 99,
                'j_menu_id'        => 0,
                'bubblewidth'      => '',
                'mapicon'          => '',
                'avatarAsIcon'     => 0,
                'avatarSizeH'      => '',
                'avatarSizeW'      => '',
                'pline'            => 0,
                'dateFormat'       => "%d-%m-%Y"
            );
        }

        GeofactoryHelperAdm::loadMultiParamFor($listVar, 2, $pk, $this);
        
        return $result;
    }
}