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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

class PlgButtonPlg_geofactory_btn_jc30 extends CMSPlugin
{
    protected $autoloadLanguage = true;

    public function onDisplay($name)
    {
        HTMLHelper::_('behavior.modal');

        $app   = Factory::getApplication();
        $input = $app->input;

        // Recupera i parametri dalla request (sostituisce JRequest)
        $option = $input->getString('option');
        $view   = $input->getString('view');
        $id     = $input->getInt('id', 0);
        $a_id   = $input->getInt('a_id', 0);
        $cid    = $input->getInt('cid', 0);

        $idArt = 0;
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
            return;
        }

        // Se l'ID dell'articolo non è ancora disponibile, genera un ID unico e lo salva nella sessione
        if ($idArt < 1) {
            $idArt = time(); // genera un ID unico basato sul timestamp
            $session = Factory::getSession();
            $session->clear('gf_temp_art_id');
            $session->set('gf_temp_art_id', $idArt);
        }

        $btntxt = Text::_('COM_GEOFACTORY_BTN_PLG_NAME');
        if ($btntxt === 'COM_GEOFACTORY_BTN_PLG_NAME') {
            $btntxt = 'Geocode';
        }

        $lat = $this->params->get('defLat', '');
        $lng = $this->params->get('defLng', '');
        $link = 'index.php?option=com_geofactory&amp;view=button&amp;tmpl=component&amp;idArt=' . $idArt . '&amp;dla=' . $lat . '&amp;dln=' . $lng . '&amp;c_opt=' . $option;
        if (strtolower(substr(JPATH_BASE, -13)) === 'administrator') {
            $link = 'index.php?option=com_geofactory&amp;view=ggmap&amp;layout=editorbtn&amp;tmpl=component&amp;idArt=' . $idArt . '&amp;dla=' . $lat . '&amp;dln=' . $lng . '&amp;c_opt=' . $option;
        }

        $button = new \stdClass;
        $button->modal   = true;
        $button->class   = 'btn';
        $button->link    = $link;
        $button->text    = $btntxt;
        $button->name    = 'location';
        $button->options = "{handler: 'iframe', size: {x: 600, y: 500}}";

        return $button;
    }
}
