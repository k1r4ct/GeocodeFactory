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
 * Gestione dei segnaposto per i template del Markerset.
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Application\CMSApplication;

class GeofactoryPlaceholderTemplates
{
    private CMSApplication $app;

    public function __construct()
    {
        $this->app = Factory::getApplication('site');
        HTMLHelper::_('bootstrap.tooltip');
    }

    /**
     * Recupera i segnaposto comuni e personalizzati
     * 
     * @return array
     */
    private function getPlaceholders(): array
    {
        $typeListe = $this->app->input->getString('type', '');

        PluginHelper::importPlugin('geocodefactory');
        $dispatcher = $this->app->getDispatcher();

        // Recupera i segnaposto personalizzati
        $placeHolders = null;
        $dispatcher->triggerEvent('getPlaceHoldersTemplate', [$typeListe, &$placeHolders]);

        $vCodes = [
            'Comuni' => [
                '{ID}'        => 'COM_GEOFACTORY_PH_VAR_ID',
                '{TITLE}'     => 'COM_GEOFACTORY_PH_VAR_TITLE',
                '{LINK}'      => 'COM_GEOFACTORY_PH_VAR_LINK',
                '{DISTANCE}'  => 'COM_GEOFACTORY_PH_VAR_DISTANCE',
                '{WAYSEARCH}' => 'COM_GEOFACTORY_PH_VAR_WAYSEARCH',
            ]
        ];

        // Aggiunge segnaposto personalizzati
        if (is_array($placeHolders) && !empty($placeHolders)) {
            foreach ($placeHolders as $h => $phs) {
                $vCodes[$h] = [];
                if (is_array($phs) && !empty($phs)) {
                    foreach ($phs as $hlp => $ph) {
                        $vCodes[$h][$ph] = $hlp;
                    }
                }
            }
        }

        return $vCodes;
    }

    /**
     * Renderizza l'HTML dei segnaposto
     * 
     * @return string
     */
    public function render(): string
    {
        $vCodes = $this->getPlaceholders();

        ob_start();
        ?>
        <fieldset>
            <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'codes']); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'codes', Text::_('COM_GEOFACTORY_PLACEHOLDERS')); ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th class="w-25 text-nowrap"><?php echo Text::_('COM_GEOFACTORY_PLACEHOLDERS_INSERT_CODE_SIDEBAR'); ?></th>
                            <th class="w-25 text-nowrap"><?php echo Text::_('COM_GEOFACTORY_PLACEHOLDERS_INSERT_CODE_TEMPLATE'); ?></th>
                            <th class="w-50 text-nowrap"><?php echo Text::_('COM_GEOFACTORY_PLACEHOLDERS_HELP'); ?></th>
                        </tr>
                    </thead>

                    <?php foreach ($vCodes as $title => $curCodes): ?>
                        <thead>
                            <tr>
                                <td colspan="3"><h3><?php echo $this->escape($title); ?></h3></td>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 0;
                            foreach ($curCodes as $code => $help):
                            ?>
                                <tr class="row<?php echo $i % 2; ?>">
                                    <td>
                                        <button type="button" 
                                                class="btn btn-info w-100" 
                                                onclick="window.parent?.addCtrlInTpl('<?php echo addslashes($code); ?>', '_b')">
                                            <?php echo $this->escape($code); ?>
                                        </button>
                                    </td>
                                    <td>
                                        <button type="button" 
                                                class="btn btn-info w-100" 
                                                onclick="window.parent?.addCtrlInTpl('<?php echo addslashes($code); ?>', '_s')">
                                            <?php echo $this->escape($code); ?>
                                        </button>
                                    </td>
                                    <td><?php echo Text::_($help); ?></td>
                                </tr>
                            <?php 
                            $i++; 
                            endforeach; 
                            ?>
                        </tbody>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'sample', Text::_('COM_GEOFACTORY_SAMPLE_TEMPLATE')); ?>
            <textarea class="form-control" rows="18">
<h2>{TITLE}</h2>
<p><a href="{LINK}">Link dettaglio</a></p>
<p>La voce è situata in: {field_city}</p>
<img src="{ICO_field_company_logo}"/>
            </textarea>

            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'sampletab', Text::_('COM_GEOFACTORY_SAMPLE_TEMPLATE_TAB')); ?>
            <textarea class="form-control" rows="18">
<div id="gftabs">
  <ul>
   <li><a href="#tab1">Titolo scheda 1</a></li>
   <li><a href="#tab2" id="SV">Titolo scheda Streetview</a></li>
   <li><a href="#tab3">Titolo scheda 3</a></li>
  </ul>
  <div id="tab1">
    Inserisci qui il contenuto html per la scheda 1
  </div>
  <div id="tab2">
   Inserisci qui il contenuto html per la scheda, in questo esempio è una scheda Streetview, 
   quindi inserisci il seguente segnaposto e NON DIMENTICARE l'id SV nel titolo della scheda come sopra
   {STREETVIEW}
  </div>
  <div id="tab3">
    Inserisci qui il contenuto html per la scheda 3
  </div>
</div>
            </textarea>

            <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
        </fieldset>
        <?php
        return ob_get_clean();
    }

    /**
     * Metodo per l'escape del testo
     * 
     * @param string $text
     * @return string
     */
    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Istanzia e renderizza
$placeholderTemplates = new GeofactoryPlaceholderTemplates();
echo $placeholderTemplates->render();