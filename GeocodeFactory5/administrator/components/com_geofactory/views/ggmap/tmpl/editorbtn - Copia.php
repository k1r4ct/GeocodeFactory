<?php
/**
 * @name        Geocode Factory - Content plugin
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @update      Daniele Bellante
 * @website     www.myJoom.com
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or die;

// Joomla 4 replacements: niente più JRequest, usiamo $app->input o Factory::getApplication()->input
$app    = Factory::getApplication();
$input  = $app->input;

$lang = Factory::getLanguage();
$lang->load('com_geofactory', JPATH_SITE);

$c_opt = $input->getCmd('c_opt', 'com_content');
$id    = $input->getInt('idArt', 0);

if ($id < 1) {
    throw new \Exception(Text::_('COM_GEOFACTORY_BTN_PLG_NO_ARTICLE_ID'), 400);
}

// Prepara la variabile di mappa
$ctx    = 'jcbtn';
$idMap  = 999;
$mapVar = $ctx . '_gf_' . $idMap;

// Aggiunge uno script inline
$js = "
function savePosition(){
    const addcode = document.getElementById('addcode').checked;
    const arData  = {};
    arData['idArt'] = document.getElementById('idArt').value;
    arData['lat']   = document.getElementById('jcbtn_lat').value;
    arData['lng']   = document.getElementById('jcbtn_lng').value;
    arData['c_opt'] = document.getElementById('jcbtn_c_opt').value;
    arData['adr']   = document.getElementById('searchPos_{$mapVar}').value;

    if(addcode){
        if(window.parent && typeof window.parent.jInsertEditorText === 'function') {
            window.parent.jInsertEditorText('{myjoom_map}');
        }
    }

    // Effettua chiamata AJAX per salvare in #__geofactory_contents
    let xhr = new XMLHttpRequest();
    let urlAjax = '" . Uri::root() . "index.php?option=com_geofactory&task=map.geocodearticle';
    let params = new URLSearchParams(arData).toString();
    xhr.open('GET', urlAjax + '&' + params);
    xhr.onload = function(){
        if(xhr.status === 200){
            // Chiudi modal
            if(window.parent && window.parent.SqueezeBox) {
                window.parent.SqueezeBox.close();
            }
        }
    };
    xhr.send();
}
";
Factory::getDocument()->addScriptDeclaration($js);

// Carica dal DB (se già geocodato)
$db = Factory::getContainer()->get(DatabaseInterface::class);

$query = $db->getQuery(true)
    ->select($db->quoteName(['address','latitude','longitude']))
    ->from($db->quoteName('#__geofactory_contents'))
    ->where($db->quoteName('id_content') . ' = ' . (int) $id)
    ->where($db->quoteName('type') . ' = ' . $db->quote($c_opt));

$db->setQuery($query, 0, 1);
$res = $db->loadObject();

$lat = '';
$lng = '';
$adr = '';

if($res) {
    $lat = $res->latitude;
    $lng = $res->longitude;
    $adr = $res->address;
}

// Richiama la funzione che disegna la mappa.  (Simile a J3) 
$map = GeofactoryExternalMapHelper::getProfileEditMap('jcbtn_lat', 'jcbtn_lng', $mapVar, true, $adr);

?>
<input type="hidden" id="jcbtn_c_opt" name="jcbtn_c_opt" value="<?php echo $c_opt; ?>" />

<div style="padding: 3px 3px 4px 6px;">
    <label for="jcbtn_lat"><?php echo Text::_('COM_GEOFACTORY_BTN_PLG_LATITUDE'); ?></label>
    <input type="text" id="jcbtn_lat" name="jcbtn_lat" value="<?php echo htmlspecialchars($lat); ?>" />

    <label for="jcbtn_lng"><?php echo Text::_('COM_GEOFACTORY_BTN_PLG_LONGITUDE'); ?></label>
    <input type="text" id="jcbtn_lng" name="jcbtn_lng" value="<?php echo htmlspecialchars($lng); ?>" />

    <label for="addcode"><?php echo Text::_('COM_GEOFACTORY_BTN_PLG_ADDCODE'); ?></label>
    <input type="checkbox" id="addcode" name="addcode" value="1" />
</div>

<?php echo $map; ?>

<div style="padding: 3px 3px 4px 6px;">
    <input type="hidden" id="idArt" value="<?php echo (int) $id; ?>"/>
    <button class="btn btn-primary" onclick="savePosition();"><?php echo Text::_('JSAVE'); ?></button>
    <button class="btn btn-secondary" onclick="if(window.parent && window.parent.SqueezeBox) { window.parent.SqueezeBox.close(); }"><?php echo Text::_('JCANCEL'); ?></button>
</div>
