<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Session\Session;

defined('_JEXEC') or die;

/**
 * Questo file di layout gestisce l'editing di una mappa nel backend.
 * In Joomla 4, molte funzioni sono simili a J3, ma con un approccio più modulare e namespaced.
 */

// Carica le behavior di Joomla (validazione form, chosen, etc.)
HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('formbehavior.chosen', 'select');
HTMLHelper::_('behavior.keepalive');

$app = Factory::getApplication();
$config = $app->getParams('com_geofactory');  // oppure: JComponentHelper::getParams('com_geofactory')
$basicMode = (int) $config->get('isBasic', 0); // basic=0 => Expert mode
$expert = ($basicMode === 0) ? GeofactoryHelperAdm::getExpertMap() : [];
$message = ($basicMode === 0)
    ? Text::_('COM_GEOFACTORY_RUNNING_BASIC') 
    : Text::_('COM_GEOFACTORY_RUNNING_EXPERT');

// Mostra un messaggio
$app->enqueueMessage($message, 'message');

?>
<style>
    /* Se usi CodeMirror in Joomla 4, personalizza pure */
    .CodeMirror {
        height: 200px !important;
    }
</style>

<script type="text/javascript">
    Joomla.submitbutton = function(task) {
        if (task === 'ggmap.cancel' || document.formvalidator.isValid(document.getElementById('ggmap-form'))) {
            Joomla.submitform(task, document.getElementById('ggmap-form'));
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        // Esempio di refresh di CodeMirror all'apertura dei tab
        const codeMirrors = document.querySelectorAll('.CodeMirror');
        // Se hai tab del tipo bootstrap 5, jQuery might be absent. Adatta l'event 'shown.bs.tab'
        document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(el => {
            el.addEventListener('shown.bs.tab', function (e) {
                codeMirrors.forEach(cm => {
                    // cm.CodeMirror.refresh();  // o simile
                });
            });
        });
    });
</script>

<form action="<?php echo $this->escape(JRoute::_('index.php?option=com_geofactory&layout=edit&id=' . (int) $this->item->id)); ?>"
      method="post" name="adminForm" id="ggmap-form" class="form-validate">
    
    <?php
    // Se la form prevede un title_alias, possiamo usare il layout standard:
    // echo LayoutHelper::render('joomla.edit.title_alias', $this);

    // Oppure stampiamo manualmente i fieldset
    ?>

    <div class="form-horizontal">
        <?php
        // Bootstrap 5: useresti un nav con nav-tabs
        // Joomla 4: puoi usare HTMLHelper::_('bootstrap.startTabSet') se stai usando ancora le classi BS4
        echo HTMLHelper::_('bootstrap.startTabSet', 'myTab', ['active' => 'general']);

        $fieldSets = $this->form->getFieldsets();

        foreach ($fieldSets as $name => $fieldSet):
            // Salta 'base' se non usi
            if ($name === 'base') {
                continue;
            }
            echo HTMLHelper::_('bootstrap.addTab', 'myTab', $name, Text::_($fieldSet->label));
            ?>
            <div class="row">
                <div class="col-md-9">
                    <?php echo $this->form->renderFieldset($name); ?>
                </div>
            </div>
            <?php
            echo HTMLHelper::_('bootstrap.endTab');
        endforeach;

        echo HTMLHelper::_('bootstrap.endTabSet');
        ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
