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
// Usa il namespace corretto per la Sidebar in Joomla 4:
use Joomla\CMS\HTML\Helpers\Sidebar;

class GeofactoryViewGgmaps extends HtmlView
{
    protected $items;
    protected $pagination;
    protected $state;
    public $sidebar;
    // Aggiungiamo la proprietà per la lista dei tipi
    protected $listTypeList;

    /**
     * Visualizza la view.
     *
     * @param   string  $tpl  Nome del template
     * @return  void
     */
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
        $this->sidebar = Sidebar::render();
        parent::display($tpl);
    }

    /**
     * Aggiunge il titolo della pagina e la toolbar.
     *
     * @return  void
     */
    protected function addToolbar()
    {
        require_once JPATH_COMPONENT . '/helpers/geofactory.php';
        $canDo = GeofactoryHelperAdm::getActions();

        ToolbarHelper::title(Text::_('COM_GEOFACTORY_MAPS_MAPS'));

        if ($canDo->get('core.create'))
        {
            ToolbarHelper::addNew('ggmap.add');
        }
        if ($canDo->get('core.edit'))
        {
            ToolbarHelper::editList('ggmap.edit');
        }
        if ($canDo->get('core.edit.state'))
        {
            ToolbarHelper::publish('ggmaps.publish', 'JTOOLBAR_PUBLISH', true);
            ToolbarHelper::unpublish('ggmaps.unpublish', 'JTOOLBAR_UNPUBLISH', true);
            ToolbarHelper::archiveList('ggmaps.archive');
            ToolbarHelper::checkin('ggmaps.checkin');
        }
        if ($this->state->get('filter.state') == -2 && $canDo->get('core.delete'))
        {
            ToolbarHelper::deleteList('', 'ggmaps.delete', 'JTOOLBAR_EMPTY_TRASH');
        }
        elseif ($canDo->get('core.edit.state'))
        {
            ToolbarHelper::trash('ggmaps.trash');
        }
        if ($canDo->get('core.admin'))
        {
            ToolbarHelper::preferences('com_geofactory');
        }

        // Configuriamo la sidebar usando la nuova API di Joomla 4.
        Sidebar::setAction('index.php?option=com_geofactory&view=ggmaps');
        Sidebar::addFilter(
            Text::_('JOPTION_SELECT_PUBLISHED'),
            'filter_state',
            HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions'), 'value', 'text', $this->state->get('filter.state'), true)
        );
        Sidebar::addFilter(
            Text::_('COM_GEOFACTORY_FILTER_MAKERSETS'),
            'filter_markerset_id',
            HTMLHelper::_('select.options', GeofactoryHelperAdm::getMarkersetsOptions(1), 'value', 'text', $this->state->get('filter.markerset_id'))
        );
    }

    /**
     * Restituisce il nome testuale corrispondente a un tipo (da _getTypeListeName)
     *
     * Se il valore passato corrisponde a uno dei valori ottenuti tramite
     * GeofactoryHelperAdm::getArrayObjTypeListe(), restituisce il testo corrispondente.
     * Altrimenti restituisce "?".
     *
     * @param   string  $test  Valore da cercare
     * @return  string
     */
    protected function _getTypeListeName($test)
    {
        if (!is_array($this->listTypeList))
        {
            $this->listTypeList = GeofactoryHelperAdm::getArrayObjTypeListe();
        }
        foreach ($this->listTypeList as $type)
        {
            if (strtolower($test) == strtolower($type->value))
            {
                return $type->text;
            }
        }
        return "?";
    }

    /**
     * Restituisce un array di campi per l'ordinamento.
     *
     * @return  array
     */
    protected function getSortFields()
    {
        return [
            'a.status' => Text::_('JSTATUS'),
            'a.name'   => Text::_('COM_GEOFACTORY_MAPS_HEADING'),
            'nbrMs'    => Text::_('COM_GEOFACTORY_MAPS_NBR_MS'),
            'a.id'     => Text::_('JGRID_HEADING_ID')
        ];
    }
}
