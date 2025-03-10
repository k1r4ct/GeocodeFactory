<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
// Usa il namespace corretto per la sidebar in Joomla 4:
use Joomla\CMS\HTML\Helpers\Sidebar;

class GeofactoryViewMarkersets extends HtmlView
{
    protected $items;
    protected $pagination;
    protected $state;
    protected $listTypeList;
    public $sidebar;

    public function display($tpl = null)
    {
        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state      = $this->get('State');

        if (count($errors = $this->get('Errors')))
        {
            throw new \Exception(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        // Configura la sidebar usando l'API di Joomla 4.
        Sidebar::setAction('index.php?option=com_geofactory&view=markersets');
        Sidebar::addFilter(
            Text::_('JOPTION_SELECT_PUBLISHED'),
            'filter_state',
            HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions'), 'value', 'text', $this->state->get('filter.state'), true)
        );
        Sidebar::addFilter(
            Text::_('COM_GEOFACTORY_FILTER_MAPS'),
            'filter_map_id',
            HTMLHelper::_('select.options', GeofactoryHelperAdm::getMapsOptions(1), 'value', 'text', $this->state->get('filter.map_id'))
        );
        Sidebar::addFilter(
            Text::_('COM_GEOFACTORY_FILTER_EXT'),
            'filter_extension',
            HTMLHelper::_('select.options', GeofactoryHelperAdm::getArrayObjTypeListe(), 'value', 'text', $this->state->get('filter.extension'))
        );
        // Renderizza la sidebar e la assegna alla variabile da usare nel layout.
        $this->sidebar = Sidebar::render();

        parent::display($tpl);
    }

    protected function addToolbar()
    {
        require_once JPATH_COMPONENT . '/helpers/geofactory.php';
        $canDo = GeofactoryHelperAdm::getActions();

        ToolbarHelper::title(Text::_('COM_GEOFACTORY_MARKERSETS_MARKERSETS'));

        if ($canDo->get('core.create'))
        {
            ToolbarHelper::addNew('markerset.add');
        }
        if ($canDo->get('core.edit'))
        {
            ToolbarHelper::editList('markerset.edit');
        }
        if ($canDo->get('core.edit.state'))
        {
            ToolbarHelper::publish('markersets.publish', 'JTOOLBAR_PUBLISH', true);
            ToolbarHelper::unpublish('markersets.unpublish', 'JTOOLBAR_UNPUBLISH', true);
            ToolbarHelper::archiveList('markersets.archive');
            ToolbarHelper::checkin('markersets.checkin');
        }

        if ($this->state->get('filter.state') == -2 && $canDo->get('core.delete'))
        {
            ToolbarHelper::deleteList('', 'markersets.delete', 'JTOOLBAR_EMPTY_TRASH');
        }
        elseif ($canDo->get('core.edit.state'))
        {
            ToolbarHelper::trash('markersets.trash');
        }

        if ($canDo->get('core.admin'))
        {
            ToolbarHelper::preferences('com_geofactory');
        }
    }

    protected function _getTypeListeName($test)
    {
        if (!is_array($this->listTypeList))
        {
            $this->listTypeList = GeofactoryHelperAdm::getArrayObjTypeListe();
        }
        foreach ($this->listTypeList as $type)
        {
            if (strtolower($test) != strtolower($type->value))
            {
                continue;
            }
            return $type->text;
        }
        return "?";
    }

    protected function getSortFields()
    {
        return [
            'ordering' => Text::_('JGRID_HEADING_ORDERING'),
            'a.status' => Text::_('JSTATUS'),
            'a.name'   => Text::_('COM_GEOFACTORY_MARKERSETS_HEADING'),
            'a.id'     => Text::_('JGRID_HEADING_ID')
        ];
    }
}
