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
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;

/**
 * View for listing oldmaps to import.
 */
class GeofactoryViewOldmaps extends HtmlView
{
    protected $items;
    protected $pagination;
    protected $state;

    public function display($tpl = null)
    {
        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state      = $this->get('State');

        // Controllo errori
        if (count($errors = $this->get('Errors'))) {
            // In Joomla 4 si può usare throw new \Exception
            // oppure Factory::getApplication()->enqueueMessage
            // Per mantenere la compatibilità minima:
            JError::raiseError(500, implode("\n", $errors));
            return false;
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        require_once JPATH_COMPONENT . '/helpers/geofactory.php';
        $canDo = GeofactoryHelperAdm::getActions();

        ToolbarHelper::title(Text::_('COM_GEOFACTORY_MAPS_IMPORT'));

        // L’icona custom si può passare come secondo arg, se preferisci
        ToolbarHelper::custom('oldmaps.import', '', '',
            Text::_('COM_GEOFACTORY_MAPS_IMPORT_NOW'),
            false
        );

        if ($canDo->get('core.admin')) {
            ToolbarHelper::preferences('com_geofactory');
        }

        // ToolbarHelper::help('COM_GEOFACTORY_HELP_XXX');
    }
}
