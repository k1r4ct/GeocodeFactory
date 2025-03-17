<?php
/**
 * @name        Geocode Factory - Content plugin
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @author      Cédric Pelloquin
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Application\CMSApplication;
use Joomla\Input\Input;

defined('_JEXEC') or die;

/**
 * Geofactory Content Button per Joomla 4.4.10 - Versione Migliorata
 */
class GeofactoryContentButtonImproved
{
    private CMSApplication $app;
    private Input $input;
    private DatabaseInterface $db;

    public function __construct()
    {
        $this->app = Factory::getApplication();
        $this->input = $this->app->input;
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);

        // Carica il file di lingua
        $lang = Factory::getLanguage();
        $lang->load('com_geofactory', JPATH_SITE);
    }

    /**
     * Metodo principale per generare il contenuto del pulsante
     * 
     * @return string
     * @throws \Exception
     */
    public function render(): string
    {
        $c_opt = $this->input->getCmd('c_opt', 'com_content');
        $id = $this->input->getInt('idArt', 0);

        if ($id < 1) {
            throw new \Exception(Text::_('COM_GEOFACTORY_BTN_PLG_NO_ARTICLE_ID'), 400);
        }

        // Prepara la variabile di mappa
        $ctx = 'jcbtn';
        $idMap = 999;
        $mapVar = $ctx . '_gf_' . $idMap;

        // Aggiungi lo script inline con Fetch API e gestione eventi
        $this->addInlineScript($mapVar);

        // Recupera dati esistenti
        $mapData = $this->getExistingMapData($id, $c_opt);

        // Genera l'HTML del pulsante e del form
        return $this->generateHTML($mapData, $c_opt, $id, $mapVar);
    }

    /**
     * Aggiunge lo script inline per salvare la posizione
     * 
     * @param string $mapVar
     */
    private function addInlineScript(string $mapVar): void
    {
        $js = <<<JS
(function(){
    function savePosition() {
        const addcode = document.getElementById('addcode').checked;
        const arData = {
            idArt: document.getElementById('idArt').value,
            lat: document.getElementById('jcbtn_lat').value,
            lng: document.getElementById('jcbtn_lng').value,
            c_opt: document.getElementById('jcbtn_c_opt').value,
            adr: document.getElementById('searchPos_{$mapVar}').value,
            token: Joomla.getOptions('csrf.token') || ''
        };

        if (addcode && window.parent && typeof window.parent.jInsertEditorText === 'function') {
            window.parent.jInsertEditorText('{myjoom_map}');
        }

        const urlAjax = '{root}index.php?option=com_geofactory&task=map.geocodearticle';
        const params = new URLSearchParams(arData).toString();
        
        fetch(urlAjax + '&' + params)
            .then(response => {
                if (response.ok) {
                    if (window.parent && window.parent.SqueezeBox) {
                        window.parent.SqueezeBox.close();
                    }
                }
            })
            .catch(error => console.error('Errore:', error));
    }

    document.addEventListener('DOMContentLoaded', function(){
        const saveBtn = document.getElementById('saveBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function(e){
                e.preventDefault();
                savePosition();
            });
        }
    });
})();
JS;
        $root = Uri::root();
        $js = str_replace('{root}', $root, $js);
        Factory::getDocument()->addScriptDeclaration($js);
    }

    /**
     * Recupera i dati mappa esistenti dal database
     * 
     * @param int $id
     * @param string $c_opt
     * @return object|null
     */
    private function getExistingMapData(int $id, string $c_opt): ?object
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['address', 'latitude', 'longitude']))
            ->from($this->db->quoteName('#__geofactory_contents'))
            ->where($this->db->quoteName('id_content') . ' = ' . $id)
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote($c_opt));

        $this->db->setQuery($query, 0, 1);
        return $this->db->loadObject();
    }

    /**
     * Genera l'HTML per il form
     * 
     * @param object|null $mapData
     * @param string $c_opt
     * @param int $id
     * @param string $mapVar
     * @return string
     */
    private function generateHTML(?object $mapData, string $c_opt, int $id, string $mapVar): string
    {
        $lat = $mapData->latitude ?? '';
        $lng = $mapData->longitude ?? '';
        $adr = $mapData->address ?? '';

        // Richiama la funzione che disegna la mappa
        $map = \GeofactoryExternalMapHelper::getProfileEditMap('jcbtn_lat', 'jcbtn_lng', $mapVar, true, $adr);

        $html = <<<HTML
<input type="hidden" id="jcbtn_c_opt" name="jcbtn_c_opt" value="{$c_opt}" />

<div class="p-2">
    <div class="mb-2">
        <label for="jcbtn_lat" class="form-label">{$this->translate('COM_GEOFACTORY_BTN_PLG_LATITUDE')}</label>
        <input type="text" class="form-control" id="jcbtn_lat" name="jcbtn_lat" value="{$this->escape($lat)}" />
    </div>

    <div class="mb-2">
        <label for="jcbtn_lng" class="form-label">{$this->translate('COM_GEOFACTORY_BTN_PLG_LONGITUDE')}</label>
        <input type="text" class="form-control" id="jcbtn_lng" name="jcbtn_lng" value="{$this->escape($lng)}" />
    </div>

    <div class="mb-2 form-check">
        <input type="checkbox" class="form-check-input" id="addcode" name="addcode" value="1" />
        <label class="form-check-label" for="addcode">{$this->translate('COM_GEOFACTORY_BTN_PLG_ADDCODE')}</label>
    </div>
</div>

{$map}

<div class="p-2">
    <input type="hidden" id="idArt" value="{$id}"/>
    <button id="saveBtn" class="btn btn-primary">{$this->translate('JSAVE')}</button>
    <button class="btn btn-secondary" onclick="if(window.parent && window.parent.SqueezeBox) { window.parent.SqueezeBox.close(); }">{$this->translate('JCANCEL')}</button>
</div>
HTML;

        return $html;
    }

    /**
     * Wrapper per Text::_() con escape
     * 
     * @param string $key
     * @return string
     */
    private function translate(string $key): string
    {
        return Text::_($key);
    }

    /**
     * Escape helper
     * 
     * @param string $text
     * @return string
     */
    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

try {
    $button = new GeofactoryContentButtonImproved();
    echo $button->render();
} catch (\Exception $e) {
    Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
}
