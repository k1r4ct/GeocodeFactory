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

// Per Joomla 4 (anche se qui c'è un check sulla versione di Joomla)
$app = Factory::getApplication();

// Carica behavior e form
HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');
HTMLHelper::_('behavior.formvalidation');
HTMLHelper::_('formbehavior.chosen', 'select');

// Se non serve più la distinzione 3.1.x / 3.2.x, puoi eliminarla.
// Qui la lascio in minima parte commentata, se desideri.
$jVersion = new \JVersion;
$major = $jVersion->getShortVersion(); // es "4.x" "3.10" ecc.

// Decidi se considerare la branch
if (version_compare($major, '3.2', '>=') || version_compare($major, '4.0', '>=')) :
    // Joomla 3.2+ o Joomla 4
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

        <?php
        // Se la form supporta 'title_alias', puoi usare:
        // echo LayoutHelper::render('joomla.edit.title_alias', $this);

        echo '<div class="form-horizontal">';
        echo HTMLHelper::_('bootstrap.startTabSet', 'myTab', array('active' => 'general'));

        // Determina quali fieldset caricare
        // (nel codice originale usava fieldSetsUsed)
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
            echo HTMLHelper::_('bootstrap.addTab', 'myTab', $name, Text::_($fieldSet->label, true));
            ?>
            <div class="row-fluid">
                <div class="span9">
                    <?php echo $this->form->getControlGroups($name); ?>
                </div>
            </div>
            <?php
            echo HTMLHelper::_('bootstrap.endTab');
        endforeach;

        echo HTMLHelper::_('bootstrap.endTabSet');
        echo '</div>';
        ?>

        <input type="hidden" name="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

<?php else : ?>
    <!-- Branch per Joomla 3.1.x (opzionale, se non serve più puoi rimuoverla) -->
    <?php
    HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');
    HTMLHelper::_('behavior.tooltip');
    HTMLHelper::_('behavior.formvalidation');
    HTMLHelper::_('formbehavior.chosen', 'select');

    // Carica fieldSets
    $fieldSetsUsed = array('base', 'general');
    if (empty($this->item->id)) {
        $fieldSetsUsed[] = 'assign-type';
    } else {
        $fieldSetsUsed[] = 'assign-type-hide';
        $fieldSetsUsed[] = 'assign-champs';
        $fieldSetsUsed[] = 'assign-address';
    }
    ?>

    <script type="text/javascript">
    Joomla.submitbutton = function(task){
        var form = document.getElementById('assign-form');
        if (task == 'assign.cancel' || document.formvalidator.isValid(form)) {
            Joomla.submitform(task, form);
        }
    }
    </script>

    <form action="<?php echo Route::_('index.php?option=com_geofactory&layout=edit&id='.(int) $this->item->id); ?>"
          method="post"
          name="adminForm"
          id="assign-form"
          class="form-validate form-horizontal">

        <ul class="nav nav-tabs">
            <?php
            $fieldSets = $this->form->getFieldsets();
            foreach ($fieldSets as $name => $fieldSet) :
                if (!in_array($name, $fieldSetsUsed)) {
                    continue;
                }
                ?>
                <li class="<?php echo ($name == 'general') ? 'active' : ''; ?>">
                    <a href="#<?php echo $name; ?>" data-toggle="tab">
                        <?php echo Text::_($fieldSet->label); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="tab-content">
            <?php
            // Renderizza i fieldset
            foreach ($fieldSetsUsed as $name) :
                ?>
                <div class="tab-pane <?php echo ($name == 'general') ? 'active' : ''; ?>"
                     id="<?php echo $name; ?>">

                    <?php foreach ($this->form->getFieldset($name) as $field) : ?>
                        <div class="control-group">
                            <div class="control-label">
                                <?php echo $field->label; ?>
                            </div>
                            <div class="controls">
                                <?php echo $field->input; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            <?php endforeach; ?>

            <input type="hidden" name="task" value="" />
            <?php echo HTMLHelper::_('form.token'); ?>
        </div>
    </form>
<?php endif; ?>
