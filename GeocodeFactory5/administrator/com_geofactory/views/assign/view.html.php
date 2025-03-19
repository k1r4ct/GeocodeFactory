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

/**
 * View for single "assign" pattern
 */
class GeofactoryViewAssign extends HtmlView
{
    protected $form;
    protected $item;
    protected $state;

    public function display($tpl = null)
    {
        $this->form  = $this->get('Form');
        $this->item  = $this->get('Item');
        $this->state = $this->get('State');

        // Controllo errori
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors), 500);
            return false;
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user       = Factory::getUser();
        $isNew      = ($this->item->id == 0);
        $checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
        $canDo      = GeofactoryHelperAdm::getActions();

        ToolbarHelper::title($isNew
            ? Text::_('COM_GEOFACTORY_ASSIGN_PATTERN_NEW')
            : Text::_('COM_GEOFACTORY_ASSIGN_PATTERN_EDIT')
        );

        if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
            ToolbarHelper::apply('assign.apply');
            ToolbarHelper::save('assign.save');
        }
        if (!$checkedOut && $canDo->get('core.create')) {
            ToolbarHelper::save2new('assign.save2new');
        }
        if (!$isNew && $canDo->get('core.create')) {
            ToolbarHelper::save2copy('assign.save2copy');
        }

        if (empty($this->item->id)) {
            ToolbarHelper::cancel('assign.cancel');
        } else {
            ToolbarHelper::cancel('assign.cancel', 'JTOOLBAR_CLOSE');
        }

        // ToolbarHelper::help('COM_GEOFACTORY_HELP_XXX');
    }
}