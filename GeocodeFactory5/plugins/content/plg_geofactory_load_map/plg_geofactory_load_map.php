<?php
/**
 * @name        Geocode Factory - Content plugin
 * @package     geoFactory
 * @copyright   Copyright © 2013-2023 - All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @author      Cédric Pelloquin <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante, Aggiornato per Joomla 4.4.10
 */

defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Language\Text;

// Includi helper necessari
require_once JPATH_ROOT . '/components/com_geofactory/helpers/geofactory.php';
require_once JPATH_SITE . '/components/com_geofactory/helpers/externalMap.php';
require_once JPATH_SITE . '/components/com_geofactory/views/map/view.html.php';
require_once JPATH_ROOT . '/administrator/components/com_geofactory/helpers/geofactory.php';
require_once JPATH_ROOT . '/administrator/components/com_geofactory/models/geocodes.php';

/**
 * Plugin per caricare le mappe nei contenuti
 */
class PlgContentPlg_geofactory_load_map extends CMSPlugin
{
    /**
     * Il codice del plugin per i pattern di sostituzione
     *
     * @var string
     */
    protected $m_plgCode = 'load_gf_map';

    /**
     * Evento onContentPrepare: sostituisce {load_gf_map ...} nel testo
     *
     * @param   string  $context     Il contesto
     * @param   object  &$article    L'articolo
     * @param   object  &$params     I parametri
     * @param   int     $limitstart  Offset per paginazione
     *
     * @return  bool    True per continuare
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

        // Proprieta text potrebbe non esistere (usare introtext in quel caso)
        $text = isset($article->text) ? $article->text : (isset($article->introtext) ? $article->introtext : '');

        // Se non c'è testo o non contiene il tag del plugin, nessuna modifica necessaria
        if (empty($text) || strpos($text, $this->m_plgCode) === false) {
            return true;
        }

        // Sostituisce il testo e lo riassegna all'articolo
        $modifiedText = $this->_prepareArticle($text, $article->id);
        
        if (isset($article->text)) {
            $article->text = $modifiedText;
        } elseif (isset($article->introtext)) {
            $article->introtext = $modifiedText;
        }

        return true;
    }

    /**
     * Prepara il testo, cercando {load_gf_map X}
     *
     * @param   string  $text  Il testo da elaborare
     * @param   int     $id    ID dell'articolo
     *
     * @return  string  Il testo modificato
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
     * @param   string  $text     Il testo
     * @param   int     $count    Numero di occorrenze
     * @param   string  $regex    Pattern regex
     * @param   array   $matches  Array di match
     *
     * @return  string  Testo con mappe sostituite
     */
    private function _replaceMap($text, $count, $regex, $matches)
    {
        for ($i = 0; $i < $count; $i++) {
            // $matches[0][$i] contiene l'intera stringa {load_gf_map 123}
            // $matches[1][$i] contiene la parte "123" dopo load_gf_map
            $idMap = trim($matches[1][$i]);
            
            // Se non è numerico o minore di 1, rimuove l'occorrenza
            if (!is_numeric($idMap) || (int)$idMap < 1) {
                $text = preg_replace($regex, '', $text, 1);
                continue;
            }

            // Carica la mappa tramite il helper esterno
            try {
                $map = GeofactoryExternalMapHelper::getMap($idMap, 'lm');
                $res = $map ? $map->formatedTemplate : '';
            } catch (\Exception $e) {
                $app = Factory::getApplication();
                if ($app->get('debug')) {
                    $res = '<div class="alert alert-danger">Error loading map: ' . $e->getMessage() . '</div>';
                } else {
                    $res = '';
                }
            }

            // Rimpiazza la prima occorrenza trovata
            $text = preg_replace($regex, $res, $text, 1);
        }
        
        return $text;
    }
}