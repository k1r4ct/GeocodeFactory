<?php
/**
 * @name      Sobipro Radius Search module
 * @package   mod_sobiproRadiusSearch
 * @copyright Copyright © 2012
 * @license   GNU/GPL
 * @author    Cédric P.
 * @website   www.myJoom.com
 * @update    Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper; // per getLayoutPath
use Joomla\CMS\Uri\Uri;

require_once JPATH_SITE . '/components/com_sobipro/lib/sobi.php';
Sobi::Initialise();

// Include il file helper una sola volta
require_once __DIR__ . '/helper.php';

// Recuperiamo l’istanza di Joomla Application
$app = Factory::getApplication();

// Sostituiamo la vecchia lettura di Itemid con la chiamata corretta
$jit = $params->get('itemid') 
    ? $params->get('itemid') 
    : $app->input->getInt('Itemid');

$sps   = $params->get('spsection') ? $params->get('spsection') : Sobi::Section();
$psp   = modsobiproRadiusSearchHelper::setGetSpParam($sps);
$dist  = modsobiproRadiusSearchHelper::getDistanceList($psp, $params->get('distshow'));
$keyw  = modsobiproRadiusSearchHelper::getKeywordMode($params->get('kwmode'));
$kwshow= modsobiproRadiusSearchHelper::getKeywordInput($params->get('kwshow'));
$rad   = modsobiproRadiusSearchHelper::getRadiusSearchForm($psp, $dist, $params->get('useTmpl'), $params->get('enterPrev'));
$btn   = modsobiproRadiusSearchHelper::getSubmitBtn($params->get('btnTxt'), $psp);
$lmb   = modsobiproRadiusSearchHelper::getLocateMeBtn($psp, $params->get('btnLocateMe'));
$tpl   = modsobiproRadiusSearchHelper::getTemplate(
    $params->get('useTmpl'),
    $params->get('tmplCode'),
    $kwshow,
    $keyw,
    $rad,
    $btn,
    $lmb
);

// Aggiunge gli script JS necessari (Google Maps ecc.)
modsobiproRadiusSearchHelper::setJsSrcipt(
    $psp,
    $params->get('def_loc'),
    $params->get('def_rad'),
    $params->get('apiKey')
);

// Carica il layout standard del modulo
require ModuleHelper::getLayoutPath('mod_sobiproradiussearch');
