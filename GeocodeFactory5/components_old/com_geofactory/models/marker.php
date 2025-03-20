<?php
/**
 * @name		Geocode Factory
 * @package		geoFactory
 * @copyright	Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author		Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website		www.myJoom.com
 */
defined('_JEXEC') or die;

class GeofactoryModelMarker extends JModelItem{
	protected $_context 	= 'com_geofactory.marker';
	protected $m_objMarkers	= array() ;
	protected $m_idMs		= 0 ;
	protected $m_objMs		= null ;
	protected $m_type		= 1 ; // 1-bubble, 2-side
	protected $m_containers = null ;

	// initialise le chargement, on charge le MS et on prépare le bon template 
	public function init($vIdM, $idMs, $vDist, $type){ 
		JPluginHelper::importPlugin('geocodefactory');
		$dsp = JDispatcher::getInstance();


		$this->m_type 		= $type ;
		$this->m_idMs		= $idMs ;
		$this->m_objMs		= GeofactoryHelper::getMs($this->m_idMs) ;


		// pour chaque marker cherche les valeurs de remplacement et complete l'object commencé avant 
		$iCountEntry = count($vIdM); // dans l'ordre d'arrivé de vIdM et vDist
		$params = array() ;
		for($i=0 ; $i<$iCountEntry ; $i++){
			// champs spéciaux et optionnels
			$params['titleField']	= (isset($this->m_objMs->field_title) AND (strlen($this->m_objMs->field_title)>0))?$this->m_objMs->field_title:null;
			$params['onlineTmpl'] 	= (isset($this->m_objMs->onlineTmp) AND (strlen($this->m_objMs->onlineTmp)>0))?$this->m_objMs->onlineTmp:null;
			$params['offlineTmpl'] 	= (isset($this->m_objMs->offlineTmp) AND (strlen($this->m_objMs->offlineTmp)>0))?$this->m_objMs->offlineTmp:null;
			$params['menuId'] 		= (isset($this->m_objMs->j_menu_id) AND (strlen($this->m_objMs->j_menu_id)>0))?$this->m_objMs->j_menu_id:null;
			$params['dateFormat'] 	= (isset($this->m_objMs->dateFormat) AND (strlen($this->m_objMs->dateFormat)>0))?$this->m_objMs->dateFormat:'';

			// création d'un objet générique avec valeurs communes
			$objMarker 				= new stdClass();
			$objMarker->replace		= array() ;
			$objMarker->search		= array() ;
			$objMarker->id 			= $vIdM[$i] ;
			$objMarker->type		= $this->m_objMs->typeList ;
			$objMarker->distance	= $vDist[$i]>0?$vDist[$i]:'';
			$objMarker->template 	= $type==1?$this->m_objMs->template_bubble:$this->m_objMs->template_sidebar ;

			// prépare le template, le marker et les remplacements
			$dsp->trigger('markerTemplateAndPlaceholder', array(&$objMarker, $params)) ;

			$this->m_objMarkers[] 	= $objMarker ;
		}
	}

	// initialise le chargement light, pour les google places car les données viennent de l'url
	public function initLt($idMs){ 
		JPluginHelper::importPlugin('geocodefactory');
		$dsp = JDispatcher::getInstance();
		$this->m_objMs = GeofactoryHelper::getMs($idMs) ;

		// création d'un objet générique avec valeurs communes
		$objMarker 				= new stdClass();
		$objMarker->replace		= array() ;
		$objMarker->search		= array() ;
		$objMarker->template 	= $this->m_objMs->template_bubble;
		$objMarker->type		= $this->m_objMs->typeList ;

		// prépare le template, le marker et les remplacements
		$params = array() ;
		$params['titleField']	= '';
		$params['onlineTmpl'] 	= '';
		$params['offlineTmpl'] 	= '';
		$params['menuId'] 		= '';
		$params['dateFormat'] 	= '';
		$dsp->trigger('markerTemplateAndPlaceholder', array(&$objMarker, $params)) ;

		$this->m_objMarkers[] 	= $objMarker ;
	}

	public function initBubbleMulti($start, $end){
		$start = strlen($start)>0?$start:'<div style="margin:2px;margin:2px;border:1px solid gray;">';
		$end = strlen($end)>0?$end:'</div>';
		$this->m_containers = array($start, $end) ;
	}

	public function loadTemplate() {
		$res = "" ;
		foreach($this->m_objMarkers as $objMarker){
			$this->_replacePlaceHolder($objMarker->template, '{ID}', 			$objMarker->id);
			$this->_replacePlaceHolder($objMarker->template, "{title}", 		$objMarker->rawTitle);
			$this->_replacePlaceHolder($objMarker->template, "{link}", 			$objMarker->link);
			$this->_replacePlaceHolder($objMarker->template, "{streetview}", 	'<div id="gf_streetView" style="width:100%;height:250px;"></div>');
			$this->_replacePlaceHolder($objMarker->template, "{locate_me}", 	JText::_('COM_GEOFACTORY_LOCATE_ME_BUBBLE'));
			$this->_replacePlaceHolder($objMarker->template, "{distance}", 		$objMarker->distance);
			$this->_replacePlaceHolder($objMarker->template, "{waysearch}", 	$this->_getWaySearch());

			// remplace les valeurs du template
			$objMarker->template = str_ireplace($objMarker->search, $objMarker->replace, $objMarker->template) ;		

			$this->_setContainer($objMarker) ;

			// passe en utf, utile pour les site en russes, chinois, ...
			if (function_exists("mb_convert_encoding"))
				$objMarker->template = mb_convert_encoding($objMarker->template, 'HTML-ENTITIES', "UTF-8");

			// si il y a des 
			if (is_array($this->m_containers) AND count($this->m_containers)>1)
				$objMarker->template = $this->m_containers[0]  . $objMarker->template . $this->m_containers[1] ;

			$res.= $objMarker->template ;
		}

		if (function_exists("mb_convert_encoding"))
			$res = mb_convert_encoding($res, 'HTML-ENTITIES', "UTF-8");

		// tente de parser les content plugin	
		// $res = JHtml::_('content.prepare', $temp);  ne fonctionn pas car ne passe pas l'id de l'article temp... donc je recrée le code
		JPluginHelper::importPlugin('content');
		$temp 		= new stdClass();
		$temp->text = $res ;
		$temp->id 	= $objMarker->id ;
		$params 	= new JObject;
		$dispatcher = JEventDispatcher::getInstance();
		$dispatcher->trigger('onContentPrepare', array('content.prepare', &$temp, &$params, 0));
		$res = $temp->text;

		return $res ;
	}

	protected function _replacePlaceHolder(&$template, $need, $value){
		// performance check inutile ... if (stripos($template, $need)===false)		return ;
		$template 	= str_ireplace($need, $value, $template);
	}

	protected function _setContainer(&$objMarker){
		$div = '<div  class="gf_bubble_container" >' ;

		// permet de fixer la largeur pour la bulle
		if ($this->m_type==1){
			$width = "" ;

			if ($this->m_objMs->bubblewidth > 0)
				$width = ' style="width:'.$this->m_objMs->bubblewidth.'px" ';
			
			$div = '<div id="gf_bubble_container_'.$this->m_idMs.'" class="gf_bubble_container" '.$width.'>' ;
		}

		$objMarker->template = $div . $objMarker->template . '</div>';			
	}

	protected function _getWaySearch(){
		$app 		= JFactory::getApplication();
		$ptcenter	= $app->input->getString('pt');

		$config			= JComponentHelper::getParams('com_geofactory');
		$to 	= JText::_('COM_GEOFACTORY_WAYSEARCH_TO') ;
		$from 	= JText::_('COM_GEOFACTORY_WAYSEARCH_FROM') ;
		$btn 	= JText::_('COM_GEOFACTORY_WAYSEARCH_BTN'); 
		$lmtxt 	= JText::_('COM_GEOFACTORY_LOCATE_ME') ;
		$center = $config->get('waysearchBtn')==1?' <img style="cursor:pointer;" id="gflmws" src="'.JURI::root().'media/com_geofactory/assets/locateme.png" alt="'.$lmtxt.'" title="'.$lmtxt.'" /> ':'';

		return	'<form class="gf_ws_form" action="http://maps.google.com/maps" method="get" target="_blank" onsubmit="submit(this);return false;">'.
				'   <input type="hidden" 	name="daddr" id="daddr"  	value="'.$ptcenter.'" />'.
				'	<input type="hidden" 	name="dir" 		value="from"><b>'.$from.'</b><br />'.
				'   <input type="text" 		class="inputbox" size="20" name="saddr" id="saddr" value="" />'.$center.'<br />'.
				'	<input value="'.$btn.'" id="gfdirws" type="button" style="margin: 2px;">'.
				'</form>';
	}
}
