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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

/**
 * View for listing geocodes in backend
 */
class GeofactoryViewGeocodes extends HtmlView
{
    protected $items;
    protected $pagination;
    protected $state;
    public    $sidebar;

    public function display($tpl = null)
    {
        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state      = $this->get('State');

        // check errors
        if (count($errors = $this->get('Errors'))) {
            JError::raiseError(500, implode("\n", $errors));
            return false;
        }

        $this->addToolbar();
        $this->sidebar = HTMLHelper::_('sidebar.render'); // In Joomla 4 di solito si usa differently, ma lasciamo la minima conversione
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        require_once JPATH_COMPONENT . '/helpers/geofactory.php';

        $canDo = GeofactoryHelperAdm::getActions();

        ToolbarHelper::title(Text::_('COM_GEOFACTORY_BATCH_GEOCODE'));

        // Prepara le liste
        $valTypes = GeofactoryHelperAdm::getArrayObjTypeListe();
        $curType  = $this->escape($this->state->get('filter.typeliste'));
        $valAssign= GeofactoryHelperAdm::getArrayObjAssign($curType);
        $assign   = $this->escape($this->state->get('filter.assign'));

        // Aggiunge i bottoni
        ToolbarHelper::custom('geocodes.geocodefiltered', 'flag', 'flag', 'COM_GEOFACTORY_GEOCODE_FILTERED', false);
        ToolbarHelper::custom('geocodes.geocodeselected', 'flag', 'flag', 'COM_GEOFACTORY_GEOCODE_SELECTED', true);

        // Esempio di un deleteList personalizzato:
        if ($curType == 'MS_K2') {
            ToolbarHelper::deleteList('Delete this position?', 'geocodes.deletek2', 'Delete position');
        }
        if ($curType == 'MS_JC') {
            ToolbarHelper::deleteList('Delete this position?', 'geocodes.deletejc', 'Delete position');
        }

        if ($canDo->get('core.admin')) {
            ToolbarHelper::preferences('com_geofactory');
            ToolbarHelper::divider();
        }
        // ToolbarHelper::help('COM_GEOFACTORY_HELP_XXX');

        HTMLHelper::_('sidebar.setAction', 'index.php?option=com_geofactory&view=geocodes');

        HTMLHelper::_('sidebar.addFilter',
            Text::_('COM_GEOFACTORY_SELECT_TYPE'),
            'typeliste',
            HTMLHelper::_('select.options', $valTypes, 'value', 'text', $curType)
        );

        HTMLHelper::_('sidebar.addFilter',
            Text::_('COM_GEOFACTORY_SELECT_PATTERN'),
            'assign',
            HTMLHelper::_('select.options', $valAssign, 'value', 'text', $assign)
        );
    }
}
