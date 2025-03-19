<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 * 
 * J'ai un seul form général. 
 *  - les params communs sont dans des onglets communs
 *  - l'onglet images est différent pour chacun
 *  - l'onglet speciphique est le meme avec une liste de champs available venant du plugin ce qui détermine ce qu'il faut dessiner.
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

if (version_compare(JVERSION, '3.2', '>='))
{
    // version 3.2.x
    HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');
    HTMLHelper::_('behavior.formvalidation');
    HTMLHelper::_('formbehavior.chosen', 'select');

    $app       = Factory::getApplication();
    $config    = ComponentHelper::getParams('com_geofactory');
    $basicMode = $config->get('isBasic'); // basic=0
    $expert    = $basicMode == 0 ? GeofactoryHelperAdm::getExpertMarkerset() : array();
    $message   = $basicMode == 0 ? Text::_('COM_GEOFACTORY_RUNNING_BASIC') : Text::_('COM_GEOFACTORY_RUNNING_EXPERT');

    // http://docs.joomla.org/Display_error_messages_and_notices
    Factory::getApplication()->enqueueMessage($message, 'message');

    // détermine quels onglets il faut charger
    $fieldSetsUsed = array('general', 'markerset-template', 'markerset-settings', 'markerset-radius');
    if (empty($this->item->id)) {
        $fieldSetsUsed[] = 'markerset-type';
        $fieldSetsUsed[] = 'markerset-type-settings-info';
    } else {
        $fieldSetsUsed[] = 'markerset-type-hide';
        $fieldSetsUsed[] = 'markerset-icon';
        $fieldSetsUsed[] = 'markerset-type-settings';
    }
    ?>
    <style>.CodeMirror{height:200px!important;}</style>
    <script type="text/javascript">
        Joomla.submitbutton = function(task){
            if (task == 'markerset.cancel' || document.formvalidator.isValid(document.getElementById('markerset-form'))) {
                Joomla.submitform(task, document.getElementById('markerset-form'));
            }
        }
        jQuery(document).ready(function(){
            var codeMirors = jQuery('.CodeMirror');
            jQuery('a[href="#markerset-template"],a[href="#markerset-settings"],a[href="#markerset-type-settings"]').on('shown', function (e) {
                codeMirors.each(function(i, el){
                    el.CodeMirror.refresh();
                });
            });
        });
    </script>

    <form action="<?php echo Route::_('index.php?option=com_geofactory&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="markerset-form" class="form-validate">
        <?php echo HTMLHelper::_('layout.render', 'joomla.edit.title_alias', $this); ?>
        <div class="form-horizontal">
            <?php echo HTMLHelper::_('bootstrap.startTabSet', 'myTab', array('active' => 'general')); ?>

                <?php
                $fieldSets = $this->form->getFieldsets();
                foreach ($fieldSets as $name => $fieldSet) :
                    if (!in_array($name, $fieldSetsUsed))
                        continue;
                ?>
                    <?php echo HTMLHelper::_('bootstrap.addTab', 'myTab', $name, Text::_($fieldSet->label, true)); ?>
                    <div class="row-fluid">
                        <div class="span9">
                            <?php echo $this->form->getControlGroups($name); ?>
                        </div>
                    </div>
                    <?php echo HTMLHelper::_('bootstrap.endTab'); ?>
                <?php endforeach; ?>
            <?php echo HTMLHelper::_('bootstrap.endTabSet'); ?>
        </div>

        <input type="hidden" name="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

    <?php

}
else
{
    // version 3.1.x
    HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');
    HTMLHelper::_('behavior.tooltip');
    HTMLHelper::_('behavior.formvalidation');
    HTMLHelper::_('formbehavior.chosen', 'select');

    $config    = ComponentHelper::getParams('com_geofactory');
    $basicMode = $config->get('isBasic'); // basic=0
    $expert    = $basicMode == 0 ? GeofactoryHelperAdm::getExpertMarkerset() : array();
    $message   = $basicMode == 0 ? Text::_('COM_GEOFACTORY_RUNNING_BASIC') : Text::_('COM_GEOFACTORY_RUNNING_EXPERT');
    $canDo     = GeofactoryHelperAdm::getActions();

    Factory::getApplication()->enqueueMessage($message, 'message');

    // détermine quels onglets il faut charger
    $fieldSetsUsed = array('base', 'general', 'markerset-template', 'markerset-settings', 'markerset-radius');
    if (empty($this->item->id)) {
        $fieldSetsUsed[] = 'markerset-type';
        $fieldSetsUsed[] = 'markerset-type-settings-info';
    } else {
        $fieldSetsUsed[] = 'markerset-type-hide';
        $fieldSetsUsed[] = 'markerset-icon';
        $fieldSetsUsed[] = 'markerset-type-settings';
    }
    ?>
    <style>.CodeMirror{height:200px!important;}</style>
    <script type="text/javascript">
        Joomla.submitbutton = function(task){
            if (task == 'markerset.cancel' || document.formvalidator.isValid(document.getElementById('markerset-form'))) {
                Joomla.submitform(task, document.getElementById('markerset-form'));
            }
        }
    </script>

    <form action="<?php echo Route::_('index.php?option=com_geofactory&layout=edit&id='.(int) $this->item->id); ?>" method="post" name="adminForm" id="markerset-form" class="form-validate form-horizontal">
        <!-- Begin Content -->
            <ul class="nav nav-tabs">
                <?php
                $fieldSets = $this->form->getFieldsets();
                foreach ($fieldSets as $name => $fieldSet) :
                    if (in_array($name, $fieldSetsUsed)) :
                ?>
                        <li <?php echo $name == "general" ? ' class="active"' : ""; ?>>
                            <a href="#<?php echo $name; ?>" data-toggle="tab"><?php echo Text::_($fieldSet->label); ?></a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content">
                <!-- Begin Tabs -->
                <?php
                foreach ($fieldSetsUsed as $name) :
                    $fieldSet = $this->form->getFieldsets($name);
                ?>
                    <div class="tab-pane<?php echo $name == "general" ? " active" : ""; ?>" id="<?php echo $name; ?>">
                    <?php foreach ($this->form->getFieldset($name) as $field) : ?>
                        <?php $display = ''; if ($basicMode && in_array($field->fieldname, $expert)) $display = 'style="display:none;"'; ?>
                        <div class="control-group" <?php echo $display; ?>>
                            <div class="control-label"><?php echo $field->label; ?></div>
                            <div class="controls"><?php echo $field->input; ?></div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <input type="hidden" name="task" value="" />
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        <!-- End Content -->
    </form>
    <?php
}
?>
