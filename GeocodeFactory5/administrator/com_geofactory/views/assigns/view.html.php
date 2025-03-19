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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * View to list the patterns assignation
 */
class GeofactoryViewAssigns extends HtmlView
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

        // Check errors
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors), 500);
            return false;
        }

        $this->addToolbar();
        $this->sidebar = $this->renderSidebar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        require_once JPATH_COMPONENT . '/helpers/geofactory.php';
        $canDo = GeofactoryHelperAdm::getActions();

        ToolbarHelper::title(Text::_('COM_GEOFACTORY_ASSIGN_PATTERN'));

        if ($canDo->get('core.create')) {
            ToolbarHelper::addNew('assign.add');
        }
        if ($canDo->get('core.edit')) {
            ToolbarHelper::editList('assign.edit');
        }
        if ($canDo->get('core.edit.state')) {
            ToolbarHelper::publish('assigns.publish', 'JTOOLBAR_PUBLISH', true);
            ToolbarHelper::unpublish('assigns.unpublish', 'JTOOLBAR_UNPUBLISH', true);
            ToolbarHelper::archiveList('assigns.archive');
            ToolbarHelper::checkin('assigns.checkin');
        }
        if ($this->state->get('filter.state') == -2 && $canDo->get('core.delete')) {
            ToolbarHelper::deleteList('', 'assigns.delete', 'JTOOLBAR_EMPTY_TRASH');
        } elseif ($canDo->get('core.edit.state')) {
            ToolbarHelper::trash('assigns.trash');
        }

        if ($canDo->get('core.admin')) {
            ToolbarHelper::preferences('com_geofactory');
        }
    }

    protected function renderSidebar()
    {
        $action = 'index.php?option=com_geofactory&view=assigns';
        
        $html = '<div class="sidebar-nav">';
        $html .= '<ul class="nav flex-column">';
        
        // Filtro per stato pubblicato
        $html .= '<li class="nav-item">';
        $html .= '<a class="nav-link" href="#">' . Text::_('JOPTION_SELECT_PUBLISHED') . '</a>';
        $html .= '<select name="filter_state" class="form-select" onchange="this.form.submit();">';
        $html .= HTMLHelper::_('select.options', 
            HTMLHelper::_('jgrid.publishedOptions'), 
            'value', 
            'text', 
            $this->state->get('filter.state'), 
            true
        );
        $html .= '</select>';
        $html .= '</li>';
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }

    protected function getSortFields()
    {
        return array(
            'a.name'      => Text::_('COM_GEOFACTORY_PATTERN_HEADING'),
            'a.typeList'  => Text::_('COM_GEOFACTORY_EXTENSION'),
            'a.id'        => Text::_('JGRID_HEADING_ID'),
        );
    }
}