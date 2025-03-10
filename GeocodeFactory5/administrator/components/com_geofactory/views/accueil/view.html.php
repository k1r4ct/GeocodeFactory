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
 * View class for Accueil (Control Panel).
 */
class GeofactoryViewAccueil extends HtmlView
{
    public function display($tpl = null)
    {
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_GEOFACTORY'));
        // ToolbarHelper::help('COM_GEOFACTORY_HELP_XXX');
    }
}
