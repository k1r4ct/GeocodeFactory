<?php
/**
 * MODEL
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Event;                  // <-- Import Joomla 4 Event class
use Joomla\Registry\Registry;

require_once JPATH_ROOT . '/components/com_geofactory/helpers/GeofactoryHelperPlus.php';

class GeofactoryModelMarker extends ItemModel
{
    protected $_context = 'com_geofactory.marker';
    protected $m_objMarkers = [];
    protected $m_idMs = 0;
    protected $m_objMs = null;
    protected $m_type = 1; // 1: bubble, 2: side
    protected $m_containers = null;

    public function getItem($pk=null){
        
        return null;
    }

    // Inizializza il caricamento: carica il marker set e prepara il template
    public function init($vIdM, $idMs, $vDist, $type)
    {
        // // Importiamo i plugin
        // PluginHelper::importPlugin('geocodefactory');
        // var_dump($vIdM);
        // Recuperiamo application e dispatcher
        $app        = Factory::getApplication();
        // $dispatcher = $app->getDispatcher();

        $this->m_type  = $type;
        $this->m_idMs  = $idMs;
        
        $iCountEntry = count($vIdM);
        $params = [];
        $params['titleField']   = (!empty($this->m_objMs->field_title))   ? $this->m_objMs->field_title   : null;
        $params['onlineTmpl']   = (!empty($this->m_objMs->onlineTmp))    ? $this->m_objMs->onlineTmp     : null;
        $params['offlineTmpl']  = (!empty($this->m_objMs->offlineTmp))   ? $this->m_objMs->offlineTmp    : null;
        $params['menuId']       = (!empty($this->m_objMs->j_menu_id))    ? $this->m_objMs->j_menu_id     : null;
        $params['dateFormat']   = (!empty($this->m_objMs->dateFormat))   ? $this->m_objMs->dateFormat    : '';
        $this->m_objMs = GeofactoryHelper::getMs($this->m_idMs);
        
        foreach ($vIdM as $i => $id) {
            $objMarker = new \stdClass();
            $objMarker->replace   = [];
            $objMarker->search    = [];
            $objMarker->id        = $id;
            $objMarker->type      = $this->m_objMs->typeList;
            $objMarker->distance  = ($vDist[$i] > 0) ? $vDist[$i] : '';
            $objMarker->template  = ($type == 1)
                ? $this->m_objMs->template_bubble
                : $this->m_objMs->template_sidebar;

            
            // $x = GeofactoryHelperPlus::getMapFields($id);
            // var_dump($x);
            
            // Rinominiamo l'evento in "onMarkerTemplateAndPlaceholder"
            // e usiamo il nuovo dispatcher->dispatch(...)
            // $event = new Event(
            //     'onMarkerTemplateAndPlaceholder',
            //     [
            //         'objMarker' => $objMarker,
            //         'params'    => $params
            //     ]
            // );
            // $dispatcher->dispatch($event);
            GeofactoryHelperPlus::markerTemplateAndPlaceholder($objMarker, $params);
            
            $this->m_objMarkers[] = $objMarker;
        }
    }

    // Inizializza il caricamento "light" per i Google Places
    public function initLt($idMs)
    {
        // PluginHelper::importPlugin('geocodefactory');
        // $app        = Factory::getApplication();
        // $dispatcher = $app->getDispatcher();

        $this->m_objMs = GeofactoryHelper::getMs($idMs);
        //??? full object ?????
        $objMarker = new \stdClass();
        $objMarker->replace   = [];
        $objMarker->search    = [];
        $objMarker->template  = $this->m_objMs->template_bubble;
        $objMarker->type      = $this->m_objMs->typeList;

        $params = [
            'titleField'  => '',
            'onlineTmpl'  => '',
            'offlineTmpl' => '',
            'menuId'      => '',
            'dateFormat'  => ''
        ];

        // Stessa logica di dispatch
        // $event = new Event(
        //     'onMarkerTemplateAndPlaceholder',
        //     [
        //         'objMarker' => $objMarker,
        //         'params'    => $params
        //     ]
        // );
        // $dispatcher->dispatch($event);
        GeofactoryHelperPlus::markerTemplateAndPlaceholder($objMarker, $params);
        $this->m_objMarkers[] = $objMarker;
    }

    public function initBubbleMulti($start, $end)
    {
        $start = (!empty($start)) ? $start : '<div style="margin:2px;border:1px solid gray;">';
        $end   = (!empty($end))   ? $end   : '</div>';
        $this->m_containers = [$start, $end];
    }

    public function loadTemplate()
    {
        $res = "";
        foreach ($this->m_objMarkers as $objMarker) {
            $this->_replacePlaceHolder($objMarker->template, '{ID}', $objMarker->id);
            $this->_replacePlaceHolder($objMarker->template, '{title}', $objMarker->rawTitle);
            $this->_replacePlaceHolder($objMarker->template, '{link}', $objMarker->link);
            $this->_replacePlaceHolder($objMarker->template, '{streetview}', '<div id="gf_streetView" style="width:100%;height:250px;"></div>');
            $this->_replacePlaceHolder($objMarker->template, '{locate_me}', Text::_('COM_GEOFACTORY_LOCATE_ME_BUBBLE'));
            $this->_replacePlaceHolder($objMarker->template, '{distance}', $objMarker->distance);
            $this->_replacePlaceHolder($objMarker->template, '{waysearch}', $this->_getWaySearch());
            
            // foreach ($placeHolders as $value => $key) {
            //     $this->_replacePlaceHolder($objMarker->template, $key, $value);
            // }
            
            $objMarker->template = str_ireplace($objMarker->search, $objMarker->replace, $objMarker->template);
            
            $this->_setContainer($objMarker);
            
            if (function_exists("mb_convert_encoding")) {
                $objMarker->template = mb_convert_encoding($objMarker->template, 'HTML-ENTITIES', "UTF-8");
            }

            if (is_array($this->m_containers) && count($this->m_containers) > 1) {
                $objMarker->template = $this->m_containers[0] . $objMarker->template . $this->m_containers[1];
            }

            $res .= $objMarker->template;
        }

        if (function_exists("mb_convert_encoding")) {
            $res = mb_convert_encoding($res, 'HTML-ENTITIES', "UTF-8");
        }

        // Prepara i plugin di contenuto
        // PluginHelper::importPlugin('content');

        $temp       = new \stdClass();
        $temp->text = $res;
        $temp->id   = isset($objMarker->id) ? $objMarker->id : 0;
        $params     = new Registry;
        
        // Usando il nuovo dispatcher per onContentPrepare
        // $app        = Factory::getApplication();
        // $dispatcher = $app->getDispatcher();
        // $event      = new Event(
        //     'onContentPrepare',
        //     [
        //         // Argomenti tipici di onContentPrepare:
        //         'context'     => 'content.prepare',
        //         'article'     => $temp,    // Prima si passava per riferimento
        //         'params'      => $params,
        //         'limitstart'  => 0,
        //     ]
        // );
        // $dispatcher->dispatch($event);
        GeofactoryHelperPlus::contentPrepare('content.prepare', $temp, $params, 0);
        return $temp->text;
    }

    protected function _replacePlaceHolder(&$template, $need, $value)
    {
        $template = str_ireplace($need, $value, $template);
    }

    protected function _setContainer(&$objMarker)
    {
        $div = '<div class="gf_bubble_container">';
        if ($this->m_type == 1) {
            $width = "";
            if (!empty($this->m_objMs->bubblewidth) && $this->m_objMs->bubblewidth > 0) {
                $width = ' style="width:' . (int)$this->m_objMs->bubblewidth . 'px" ';
            }
            $div = '<div id="gf_bubble_container_' . $this->m_idMs . '" class="gf_bubble_container" ' . $width . '>';
        }
        $objMarker->template = $div . $objMarker->template . '</div>';
    }

    protected function _getWaySearch()
    {
        $app      = Factory::getApplication('site');
        $ptcenter = $app->input->getString('pt');

        $config = \Joomla\CMS\Component\ComponentHelper::getParams('com_geofactory');
        $to     = Text::_('COM_GEOFACTORY_WAYSEARCH_TO');
        $from   = Text::_('COM_GEOFACTORY_WAYSEARCH_FROM');
        $btn    = Text::_('COM_GEOFACTORY_WAYSEARCH_BTN');
        $lmtxt  = Text::_('COM_GEOFACTORY_LOCATE_ME');

        $center = '';
        if ((int)$config->get('waysearchBtn') === 1) {
            $center = ' <img style="cursor:pointer;" id="gflmws" src="' . Uri::root()
                    . 'media/com_geofactory/assets/locateme.png" alt="' . $lmtxt
                    . '" title="' . $lmtxt . '" /> ';
        }

        return '<form class="gf_ws_form" action="http://maps.google.com/maps" method="get" target="_blank" onsubmit="submit(this);return false;">'
            . '<input type="hidden" name="daddr" id="daddr" value="' . $ptcenter . '" />'
            . '<input type="hidden" name="dir" value="from"><b>' . $from . '</b><br />'
            . '<input type="text" class="inputbox" size="20" name="saddr" id="saddr" value="" />'
            . $center . '<br />'
            . '<input value="' . $btn . '" id="gfdirws" type="button" style="margin: 2px;">'
            . '</form>';
    }
}
