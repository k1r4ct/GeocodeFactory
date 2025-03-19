<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 * 
 * Gestione del form di modifica del Markerset.
 * - I parametri comuni sono in schede condivise
 * - La scheda delle immagini è diversa per ciascun tipo
 * - La scheda specifica è la stessa con un elenco di campi disponibili dal plugin
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

// Carica i comportamenti di Joomla
HTMLHelper::_('bootstrap.framework');
// Sostituzione form.validate con behavior.formvalidator
HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('formbehavior.chosen', 'select');

$app    = Factory::getApplication();
$config = ComponentHelper::getParams('com_geofactory');
$basicMode = (int)$config->get('isBasic', 0); // basic=0 => Modalità Esperto
$expert = ($basicMode === 0) ? GeofactoryHelperAdm::getExpertMarkerset() : [];
$message = ($basicMode === 0)
    ? Text::_('COM_GEOFACTORY_RUNNING_BASIC')
    : Text::_('COM_GEOFACTORY_RUNNING_EXPERT');

// Mostra un messaggio di configurazione
$app->enqueueMessage($message, 'message');

// Definisce le schede da caricare
$fieldSetsUsed = [
    'general',
    'markerset-template',
    'markerset-settings',
    'markerset-radius'
];

if (empty($this->item->id)) {
    $fieldSetsUsed[] = 'markerset-type';
    $fieldSetsUsed[] = 'markerset-type-settings-info';
} else {
    $fieldSetsUsed[] = 'markerset-type-hide';
    $fieldSetsUsed[] = 'markerset-icon';
    $fieldSetsUsed[] = 'markerset-type-settings';
}
?>
<style>
    .CodeMirror { 
        height: 200px !important; 
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('markerset-form');
    const codeMirrors = document.querySelectorAll('.CodeMirror');
    const tabs = document.querySelectorAll('button[data-bs-toggle="tab"]');

    // Gestione submit del form
    Joomla.submitbutton = function(task) {
        if (task === 'markerset.cancel' || form.formvalidator.isValid(form)) {
            Joomla.submitform(task, form);
        }
    };

    // Refresh CodeMirror all'apertura delle schede
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function () {
            codeMirrors.forEach(cm => {
                if (cm.CodeMirror) {
                    cm.CodeMirror.refresh();
                }
            });
        });
    });
});
</script>

<form action="<?php echo Route::_('index.php?option=com_geofactory&layout=edit&id=' . (int) $this->item->id); ?>" 
      method="post" 
      name="adminForm" 
      id="markerset-form" 
      class="form-validate">

    <?php echo HTMLHelper::_('layout.render', 'joomla.edit.title_alias', $this); ?>

    <div class="container-fluid">
        <?php 
        echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'general']); 

        $fieldSets = $this->form->getFieldsets();
        foreach ($fieldSets as $name => $fieldSet) :
            if (!in_array($name, $fieldSetsUsed)) {
                continue;
            }
        ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', $name, Text::_($fieldSet->label, true)); ?>
            <div class="row">
                <div class="col-md-9">
                    <?php 
                    foreach ($this->form->getFieldset($name) as $field) : 
                        // Gestione campi esperti in modalità base
                        $isExpertField = $basicMode === 0 && in_array($field->fieldname, $expert);
                        $fieldClasses = $isExpertField ? 'd-none' : '';
                    ?>
                        <div class="mb-3 <?php echo $fieldClasses; ?>">
                            <div class="form-label"><?php echo $field->label; ?></div>
                            <div><?php echo $field->input; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endforeach; ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
