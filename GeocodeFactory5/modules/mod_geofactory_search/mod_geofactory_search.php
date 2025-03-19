<?php
/**
 * @name        Geocode Factory Search module
 * @package     mod_geofactory_search
 * @copyright   Copyright © 2014-2023 - All rights reserved.
 * @license     GNU/GPL
 * @author      Cédric Pelloquin
 * @author mail  info@myJoom.com
 * @update      Daniele Bellante, Aggiornato per Joomla 4.4.10
 * @website     www.myJoom.com
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

require_once __DIR__ . '/helper.php';

// Inizializza l'helper
$helper = new ModGeofactorySearchHelper();

// Configurazione necessaria per API Google Maps
$helper->setJsInit($params);

// Recupera i dati necessari per il modulo
$labels      = $helper->getLabels($params);
$lmb         = $helper->getLocateMeBtn($params);
$radInpHtml  = $helper->getRadiusInput($params, $lmb);
$radDistHtml = $helper->getRadiusDistances($params);
$radIntro    = $helper->getRadiusIntro($params);
$buttons     = $helper->getButtons($params, $labels);
$barHtml     = $helper->getSideBar($params);
$listHtml    = $helper->getSideLists($params);

// Carica il template del modulo
require ModuleHelper::getLayoutPath('mod_geofactory_search', $params->get('layout', 'default'));