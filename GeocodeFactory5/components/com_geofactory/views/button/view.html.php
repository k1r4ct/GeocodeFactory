<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © ...
 * @license     ...
 * @author      ...
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;

/**
 * View class for the Button View (Joomla 4 style)
 */
class GeofactoryViewButton extends HtmlView
{
    /**
     * Esegue il rendering della vista "button"
     *
     * @param   string  $tpl  Name of the template file to parse
     * @return  mixed
     */
    public function display($tpl = null)
    {
        // Se hai bisogno di logica speciale, la aggiungi qui.
        // Altrimenti, semplicemente:
        return parent::display($tpl);
    }
}
