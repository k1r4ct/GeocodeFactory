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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Layout\LayoutHelper;

// Preparazione per Joomla 4
$app = Factory::getApplication();

// Carica behavior e form
HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');
HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('formbehavior.chosen', 'select');

?>
<script type="text/javascript">
Joomla.submitbutton = function(task){
    var form = document.getElementById('assign-form');
    if (task == 'assign.cancel' || document.formvalidator.isValid(form)) {
        Joomla.submitform(task, form);
    }
}
</script>

<form action="<?php echo Route::_('index.php?option=com_geofactory&layout=edit&id=' . (int) $this->item->id); ?>"
      method="post"
      name="adminForm"
      id="assign-form"
      class="form-validate">

    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>

    <div class="row">
        <div class="col-lg-9">
            <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', array('active' => 'general')); ?>

            <?php
            // Determina quali fieldset caricare
            $fieldSetsUsed = array('general');
            if (empty($this->item->id)) {
                $fieldSetsUsed[] = 'assign-type';
            } else {
                $fieldSetsUsed[] = 'assign-type-hide';
                $fieldSetsUsed[] = 'assign-champs';
                $fieldSetsUsed[] = 'assign-address';
            }

            $fieldSets = $this->form->getFieldsets();
            foreach ($fieldSets as $name => $fieldSet) :
                if (!in_array($name, $fieldSetsUsed)) {
                    continue;
                }
                echo HTMLHelper::_('uitab.addTab', 'myTab', $name, Text::_($fieldSet->label, true));
                ?>
                <div class="row">
                    <div class="col-12 col-lg-9">
                        <?php echo $this->form->renderFieldset($name); ?>
                    </div>
                </div>
                <?php
                echo HTMLHelper::_('uitab.endTab');
            endforeach;

            echo HTMLHelper::_('uitab.endTabSet');
            ?>
        </div>
        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <?php echo LayoutHelper::render('joomla.edit.global', $this); ?>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>