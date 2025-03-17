<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;

/**
 * View class for Terms (accept/decline).
 */
class GeofactoryViewTerms extends HtmlView
{
    public function display($tpl = null)
    {
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        // Imposta il titolo nella toolbar
        ToolbarHelper::title(Text::_('COM_GEOFACTORY_TERMS_WELCOME'));
        // Se serve un pulsante di aiuto, puoi decommentare la riga seguente:
        // ToolbarHelper::help('COM_GEOFACTORY_HELP_TERMS');
    }
}
