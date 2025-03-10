<?php
/**
 * @name        Geocode Factory - Content plugin
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @author      Cédric Pelloquin <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseDriver;

// In Joomla 4 non è necessario jimport('joomla.plugin.plugin')

require_once JPATH_ROOT . '/components/com_geofactory/helpers/geofactory.php';
require_once JPATH_SITE . '/components/com_geofactory/helpers/externalMap.php';
require_once JPATH_SITE . '/components/com_geofactory/views/map/view.html.php';
require_once JPATH_ROOT . '/administrator/components/com_geofactory/helpers/geofactory.php';
require_once JPATH_ROOT . '/administrator/components/com_geofactory/models/geocodes.php';

class PlgContentPlg_geofactory_load_map extends CMSPlugin
{
    protected $m_plgCode = 'load_gf_map';

    /**
     * Constructor.
     *
     * @param   object  &$subject  The object to observe
     * @param   array   $config    An array that holds the plugin configuration
     */
    public function __construct(&$subject, $config = [])
    {
        parent::__construct($subject, $config);
    }

    /**
     * Evento onContentPrepare: sostituisce {load_gf_map ...} nel testo
     *
     * @param   string  $context
     * @param   object  &$article
     * @param   object  &$params
     * @param   int     $limitstart
     *
     * @return  bool
     */
    public function onContentPrepare($context, &$article, &$params, $limitstart = 0)
    {
        // Non esegue quando si indicizza
        if ($context === 'com_finder.indexer') {
            return true;
        }

        // Se per qualche motivo non è un oggetto con ->id
        if (!is_object($article) || !isset($article->id)) {
            return false;
        }

        // Sostituisce nel testo: controlla se esiste la proprietà 'text', altrimenti 'introtext'
        if (isset($article->text)) {
            $article->text = $this->_prepareArticle($article->text, $article->id);
        } elseif (isset($article->introtext)) {
            $article->introtext = $this->_prepareArticle($article->introtext, $article->id);
        }
        return true;
    }

    /**
     * Prepara il testo, cercando {load_gf_map X}
     *
     * @param   string  $text
     * @param   int     $id
     *
     * @return  string
     */
    private function _prepareArticle($text, $id)
    {
        // Se il codice del plugin non è presente, ritorna il testo originale
        if (strpos($text, $this->m_plgCode) === false) {
            return $text;
        }

        // Trova {load_gf_map 123} (o simile)
        $regex = '/{load_gf_map\s+(.*?)}/i';

        // Se il plugin è disabilitato, rimuove direttamente il codice
        if (!$this->params->get('enabled', 1)) {
            return preg_replace($regex, '', $text);
        }

        // Trova tutte le occorrenze
        preg_match_all($regex, $text, $matches);
        $count = count($matches[0]);

        if ($count) {
            $text = $this->_replaceMap($text, $count, $regex, $matches);
        }

        return $text;
    }

    /**
     * Sostituisce ciascuna occorrenza con la mappa corrispondente
     *
     * @param   string  $text
     * @param   int     $count
     * @param   string  $regex
     * @param   array   $matches
     *
     * @return  string
     */
    private function _replaceMap($text, $count, $regex, $matches)
    {
        for ($i = 0; $i < $count; $i++) {
            // $matches[0][$i] contiene l’intera stringa {load_gf_map 123}
            // $matches[1][$i] contiene la parte "123" dopo load_gf_map
            $idMap = trim($matches[1][$i]);
            // Se non è numerico o minore di 1, rimuove l'occorrenza
            if (!is_numeric($idMap) || (int)$idMap < 1) {
                $text = preg_replace($regex, '', $text, 1);
                continue;
            }

            // Carica la mappa tramite il helper esterno
            $map = GeofactoryExternalMapHelper::getMap($idMap, 'lm');
            $res = $map->formatedTemplate;

            // Rimpiazza la prima occorrenza trovata
            $text = preg_replace($regex, $res, $text, 1);
        }
        return $text;
    }
}
