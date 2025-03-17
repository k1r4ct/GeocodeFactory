<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @author      
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Component\ComponentHelper;  // <--- Import necessario
use Joomla\Component\Geofactory\Administrator\Helper\GeofactoryHelper;

/**
 * Questo file di layout gestisce l'editing di una mappa nel backend.
 * Ottimizzato per Joomla 4.4.10 e Bootstrap 5.
 */

// Carica i comportamenti di Joomla
HTMLHelper::_('bootstrap.framework');
// Sostituzione form.validate con behavior.formvalidator
HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('formbehavior.chosen', 'select');

// Ottieni l'application (se serve per i messaggi) e i parametri del componente
$app = Factory::getApplication();
// In Joomla 4, getParams() non esiste più nell'app Administrator, quindi usiamo ComponentHelper:
$config = ComponentHelper::getParams('com_geofactory');

$basicMode = (int) $config->get('isBasic', 0); // basic=0 => Expert mode
$expert = ($basicMode === 0) ? GeofactoryHelper::getExpertMap() : [];
$message = ($basicMode === 0)
    ? Text::_('COM_GEOFACTORY_RUNNING_BASIC') 
    : Text::_('COM_GEOFACTORY_RUNNING_EXPERT');

// Mostra un messaggio
$app->enqueueMessage($message, 'message');
?>
<style>
    .CodeMirror {
        height: 200px !important;
    }
</style>

<script>
    Joomla.submitbutton = function(task) {
        const form = document.getElementById('ggmap-form');
        if (task === 'ggmap.cancel' || form.formvalidator.isValid(form)) {
            Joomla.submitform(task, form);
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        const codeMirrors = document.querySelectorAll('.CodeMirror');
        const tabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
        
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
      id="ggmap-form" 
      class="form-validate">
    
    <div class="container-fluid">
        <?php
        echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'general', 'recall' => true]);

        $fieldSets = $this->form->getFieldsets();

        foreach ($fieldSets as $name => $fieldSet):
            // Salta 'base' se non usi
            if ($name === 'base') {
                continue;
            }
            
            echo HTMLHelper::_('uitab.addTab', 'myTab', $name, Text::_($fieldSet->label));
            ?>
            <div class="row">
                <div class="col-md-9">
                    <?php echo $this->form->renderFieldset($name); ?>
                </div>
            </div>
            <?php
            echo HTMLHelper::_('uitab.endTab');
        endforeach;

        echo HTMLHelper::_('uitab.endTabSet');
        ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
