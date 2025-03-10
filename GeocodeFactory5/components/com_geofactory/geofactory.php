<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 *
 * Differences/limitations : 
 *      affiche du marker de l'entrée courante; non viene visualizzata un'icona personalizzata
 *      oppure viene gestita via JavaScript; 
 *      inoltre, plines tra gli eventi e gli iscritti.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

require_once JPATH_COMPONENT . '/helpers/route.php';

$controller = BaseController::getInstance('Geofactory');
$controller->execute(Factory::getApplication()->input->get('task'));
$controller->redirect();
