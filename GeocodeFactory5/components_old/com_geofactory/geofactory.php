<?php
/**
 * @name		Geocode Factory
 * @package		geoFactory
 * @copyright	Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author		Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website		www.myJoom.com

 	Differences/limitations : 
		affiche du marker de l'entrée courante on affiche pas une icone perso ... ou alors depuis javascript
		plines entre les events et les inscrits

 */
defined('_JEXEC') or die;
require_once JPATH_COMPONENT.'/helpers/route.php';

$controller	= JControllerLegacy::getInstance('Geofactory');
$controller->execute(JFactory::getApplication()->input->get('task'));
$controller->redirect();
