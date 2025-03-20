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
require_once JPATH_COMPONENT.'/helpers/geofactory.php';

class GeofactoryControllerMarker extends JControllerLegacy{
	protected $text_prefix = 'COM_GEOFACTORY';

	// la bulle
	public function bubble(){
		$app 		= JFactory::getApplication();
		$idM		= $app->input->getInt('idU', -1);
		$idMs		= $app->input->getInt('idL', -1);
		$dist		= $app->input->getFloat('dist', -1);

		$model 		= $this->getModel('Marker');
		$vids 		= array($idM) ;
		$vDist		= array($dist) ;

		$model->init($vids, $idMs, $vDist, 1) ; 
		$content 	= $model->loadTemplate() ;

		ob_clean();flush();
		echo $content ;
		$app->close();
	}

	// bulle pour les nouveaux Google place
	public function bubblePl(){
		$app 		= JFactory::getApplication();
		$dist		= $app->input->getFloat('dist', -1); 
		$idMs		= $app->input->getInt('idL', -1);

		$model 		= $this->getModel('Marker');
		$model->initLt($idMs) ; 
		$content 	= $model->loadTemplate() ;

		ob_clean();flush();
		echo $content ;
		$app->close();
	}

	// un marker à la fois
	public function side(){
		$app 		= JFactory::getApplication();
		$idM		= $app->input->getInt('idU', -1);
		$idMs		= $app->input->getInt('idL', -1);
		$dist		= $app->input->getFloat('dist', -1);

		$model 		= $this->getModel('Marker');
		$vids 		= array($idM) ;
		$vDist		= array($dist) ;

		$model->init($vids, $idMs, $vDist, 2) ; 
		$content 	= $model->loadTemplate() ;

		ob_clean();flush();
		echo $content ;
		$app->close();
	}

	// tout les markers d'une fois
	public function fullSide(){
		$app 		= JFactory::getApplication();
		$json		= $app->input->getVar('idsDists');
		$idMs		= $app->input->getInt('idL', -1);

		$model 		= $this->getModel('Marker');
		$brutes		= json_decode($json, true) ;
		
		if (is_string($brutes))
			$brutes		= explode(',', $brutes);
		$vIds 		= array() ;
		$vDist		= array() ;

		// doit etre un array, plus grand que 1 et divisible par 0
		$content 	= "" ;
		if (is_array($brutes) AND count($brutes)>1 AND count($brutes)%2 == 0){
			$max = count($brutes) ;
			for($i=0 ; $i<$max ; $i++){
				$vIds[] 	= (int)$brutes[$i] ;
				$i++ ;
				$vDist[]	= (float)$brutes[$i] ;
			}

			$model->init($vIds, $idMs, $vDist, 2) ; 
			$content 	= $model->loadTemplate() ;
		}

		ob_clean();flush();
		echo $content ;
		$app->close();
	}

	// markers a la meme place, deux ou plus bulle en une
	// --> si bug voir GF3 j'ai fait une correction dans axloadbubbleOnce, mais je sais plus quoi.
	public function bubbleMulti(){
		$app 		= JFactory::getApplication();

		$json		= $app->input->getVar('idsDists');
		$idMs		= $app->input->getInt('idL', -1);
		$model 		= $this->getModel('Marker');

		$brutes		= null;
		$config = JComponentHelper::getParams('com_geofactory');
		if ($config->get('multibubble_json') == 1)
			$brutes		= json_decode($json, true) ; // sur certains serveurs, l'ajout de "" fait planter l'url ... certainement un soucis de SEF ou htaccess
		else
			$brutes		= $json ;

		$brutes		= explode(',', $brutes);
		$vIds 		= array() ;
		$vDist		= array() ;

		$start 	= JText::_('COM_GEOFACTORY_AROUND_MULTI_BUBBLE_1') ;
		$end 	= JText::_('COM_GEOFACTORY_AROUND_MULTI_BUBBLE_2') ;



		// doit etre un array, plus grand que 1 et divisible par 2 
		$content = "" ;
		if (is_array($brutes) AND count($brutes)>1 AND count($brutes)%2 == 0){
			$max = count($brutes) ;
			for($i=0 ; $i<$max ; $i++){
				$vIds[] 	= (int)$brutes[$i] ;
				$i++ ;
				$vDist[]	= (float)$brutes[$i] ;
			}

			$model->init($vIds, $idMs, $vDist, 1) ; 
			$model->initBubbleMulti($start, $end) ;
			$content = $model->loadTemplate() ;
		}

		// ajout du titre
		$titre = JText::_('COM_GEOFACTORY_THEREIS_X_ENTRIES_HERE') ;
		if (strlen($titre)>2){
			$titre = sprintf($titre, count($vIds)) ;
			$content = $titre . $content ;
		}

		ob_clean();flush();
		echo $content ;
		$app->close();
	}
}
