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

JLoader::register('GeofactoryHelper', JPATH_COMPONENT . '/helpers/geofactory.php');

class GeofactoryViewMarkerset extends HtmlView
{
    protected $form;
    protected $item;
    protected $state;

    /**
     * Display the view.
     *
     * @param   string  $tpl  Template file name
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        $this->form  = $this->get('Form');
        $this->item  = $this->get('Item');
        $this->state = $this->get('State');

        if (count($errors = $this->get('Errors')))
        {
            throw new Exception(implode("\n", $errors), 500);
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     */
    protected function addToolbar()
    {
        \Joomla\CMS\Factory::getApplication()->input->set('hidemainmenu', true);

        $user       = \Joomla\CMS\Factory::getUser();
        $isNew      = ($this->item->id == 0);
        $checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
        $canDo      = GeofactoryHelperAdm::getActions();

        ToolbarHelper::title($isNew ? Text::_('COM_GEOFACTORY_MARKERSETS_NEW_MARKERSET') : Text::_('COM_GEOFACTORY_MARKERSETS_EDIT_MARKERSET'));

        if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create')))
        {
            ToolbarHelper::apply('markerset.apply');
            ToolbarHelper::save('markerset.save');
        }
        if (!$checkedOut && $canDo->get('core.create'))
        {
            ToolbarHelper::save2new('markerset.save2new');
        }
        if (!$isNew && $canDo->get('core.create'))
        {
            ToolbarHelper::save2copy('markerset.save2copy');
        }
        if (empty($this->item->id))
        {
            ToolbarHelper::cancel('markerset.cancel');
        }
        else
        {
            ToolbarHelper::cancel('markerset.cancel', 'JTOOLBAR_CLOSE');
        }

        // ToolbarHelper::divider();
        // ToolbarHelper::help('COM_GEOFACTORY_HELP_XXX');
    }
}
