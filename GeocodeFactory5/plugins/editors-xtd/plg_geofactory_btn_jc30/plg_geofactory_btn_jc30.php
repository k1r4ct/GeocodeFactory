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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Session\Session;

/**
 * Editor button plugin per Geocode Factory
 */
class PlgButtonPlg_geofactory_btn_jc30 extends CMSPlugin
{
    /**
     * Carica automaticamente il file di lingua
     *
     * @var boolean
     */
    protected $autoloadLanguage = true;

    /**
     * Mostra il pulsante nell'editor
     *
     * @param   string  $name  Nome dell'editor
     * @return  CMSObject|null  Oggetto pulsante o null
     */
    public function onDisplay($name)
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $wa = $app->getDocument()->getWebAssetManager();
        
        // Registriamo gli asset Bootstrap per la modale
        $wa->useScript('bootstrap.modal');

        // Recupera i parametri dalla request
        $option = $input->getString('option');
        $view = $input->getString('view');
        $id = $input->getInt('id', 0);
        $a_id = $input->getInt('a_id', 0);
        $cid = $input->getInt('cid', 0);

        $idArt = 0;
        
        // Determina l'ID dell'articolo in base al contesto
        if (strcasecmp($option, 'com_content') === 0) {
            if (strcasecmp($view, 'article') === 0) {
                $idArt = $id;
            }
            if (strcasecmp($view, 'form') === 0) {
                $idArt = $a_id;
            }
        } elseif (strcasecmp($option, 'com_k2') === 0) {
            if ((strcasecmp($view, 'item') === 0) && $id > 0) {
                $idArt = $id; // frontend
            }
            if ((strcasecmp($view, 'item') === 0) && $cid > 0) {
                $idArt = $cid; // backend
            }
        } else {
            // Se non siamo in un contesto di editing supportato, non mostrare il pulsante
            return null;
        }

        // Se l'ID dell'articolo non è ancora disponibile, genera un ID unico e lo salva nella sessione
        if ($idArt < 1) {
            $idArt = time(); // genera un ID unico basato sul timestamp
            $session = $app->getSession();
            $session->clear('gf_temp_art_id');
            $session->set('gf_temp_art_id', $idArt);
        }

        // Testo del pulsante
        $btntxt = Text::_('COM_GEOFACTORY_BTN_PLG_NAME');
        if ($btntxt === 'COM_GEOFACTORY_BTN_PLG_NAME') {
            $btntxt = 'Geocode';
        }

        // Coordinate predefinite
        $lat = $this->params->get('defLat', '');
        $lng = $this->params->get('defLng', '');
        
        // Determina l'URL per il popup
        $link = 'index.php?option=com_geofactory&amp;view=button&amp;tmpl=component&amp;idArt=' . $idArt . '&amp;dla=' . $lat . '&amp;dln=' . $lng . '&amp;c_opt=' . $option;
        
        // Se siamo nel backend, usa un altro layout
        if ($app->isClient('administrator')) {
            $link = 'index.php?option=com_geofactory&amp;view=ggmap&amp;layout=editorbtn&amp;tmpl=component&amp;idArt=' . $idArt . '&amp;dla=' . $lat . '&amp;dln=' . $lng . '&amp;c_opt=' . $option;
        }

        // Crea l'oggetto pulsante
        $button = new CMSObject;
        $button->modal = true;
        $button->link = $link;
        $button->text = $btntxt;
        $button->name = 'geo-marker';
        $button->icon = 'map-marker';
        $button->iconSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6"/></svg>';
        $button->options = [
            'height' => 500,
            'width' => 800,
            'bodyHeight' => 70,
            'modalWidth' => 80,
        ];

        return $button;
    }
}