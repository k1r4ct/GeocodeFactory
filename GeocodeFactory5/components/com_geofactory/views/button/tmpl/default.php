<?php
/**
 * @name        Geocode Factory - Content plugin
 * @package     geoFactory
 * @copyright   Copyright © ...
 * @license     GNU General Public License ...
 * @author      ...
 * @update		Daniele Bellante
 * @website     www.myJoom.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

// Carica il framework jQuery (Joomla 4)
HTMLHelper::_('jquery.framework');

// Carica l'helper esterno (se esiste in Joomla 4)
require_once JPATH_ROOT . '/components/com_geofactory/helpers/externalMap.php';

$app  = Factory::getApplication();
$lang = $app->getLanguage();
$lang->load('com_geofactory', JPATH_SITE);

// Component di default
$c_opt = $app->input->getString('c_opt', 'com_content');

// Verifica ID articolo
$id = $app->input->getInt('idArt', 0);
if ($id < 1) {
    // In Joomla 4, non si usa JError::raiseError. Possiamo fare:
    $app->enqueueMessage(Text::_('COM_GEOFACTORY_BTN_PLG_NO_ARTICLE_ID'), 'error');
    return;
}

// Prepara alcune variabili
$ctx   = 'jcbtn';
$idMap = 999;
$mapVar = $ctx . '_gf_' . $idMap;

// Genera lo script JavaScript
$root = Uri::root();
$js = <<<JS
function savePosition(){
    var arData      = {};
    var addcode     = jQuery('#addcode').prop('checked');
    arData['idArt'] = jQuery('#idArt').val();
    arData['lat']   = jQuery('#jcbtn_lat').val();
    arData['lng']   = jQuery('#jcbtn_lng').val();
    arData['c_opt'] = jQuery('#jcbtn_c_opt').val();
    arData['adr']   = jQuery('#searchPos_{$mapVar}').val();

    if (addcode) {
        window.parent.jInsertEditorText('{myjoom_map}');
    }

    jQuery.ajax({
        url: '{$root}index.php?option=com_geofactory&task=map.geocodearticle',
        data: arData,
        success: function(data){
            // Attenzione: SqueezeBox è stato rimosso in Joomla 4,
            // se lo vuoi ancora usare, devi includerlo manualmente
            // o sostituire con un'altra libreria (modale Bootstrap?)
            if (window.parent.SqueezeBox) {
                window.parent.SqueezeBox.close();
            } else {
                // fallback: chiude la finestra modale in qualche altro modo
                console.log("SqueezeBox not found. Modal may not close automatically.");
            }
        }
    });
}
JS;
Factory::getDocument()->addScriptDeclaration($js);

// Valori di default
$lat = '';
$lng = '';
$adr = '';

// Connessione al DB
/** @var DatabaseInterface $db */
$db = Factory::getDbo();

// Verifica coordinate già esistenti
$query = $db->getQuery(true)
    ->select($db->quoteName(['address', 'latitude', 'longitude']))
    ->from($db->quoteName('#__geofactory_contents'))
    ->where('id_content = ' . (int)$id)
    ->where('type = ' . $db->quote($c_opt));

$db->setQuery($query, 0, 1);
try {
    $res = $db->loadObjectList();
} catch (\RuntimeException $e) {
    // Errore di query
    throw new \RuntimeException('Database error: ' . $e->getMessage(), 500);
}

if (count($res) > 0) {
    $lat = $res[0]->latitude;
    $lng = $res[0]->longitude;
    $adr = $res[0]->address;
}

// Disegna la mappa con l'helper
// In Joomla 4, vanno rimossi i riferimenti a JRequest. Usa $app->input->getString().
$dla = $app->input->getString('dla', '');
$dln = $app->input->getString('dln', '');

$map = GeofactoryExternalMapHelper::getProfileEditMap(
    'jcbtn_lat',
    'jcbtn_lng',
    $mapVar,
    true,
    $adr,
    [],
    [],
    $dla . ',' . $dln
);
?>
<input type="hidden" id="jcbtn_c_opt" name="jcbtn_c_opt" value="<?php echo $c_opt; ?>" />

<ul style="padding: 3px 3px 4px 6px;">
    <li>
        <label for="jcbtn_lat"><?php echo Text::_('COM_GEOFACTORY_BTN_PLG_LATITUDE'); ?></label>
        <input type="text" id="jcbtn_lat" name="jcbtn_lat" value="<?php echo htmlspecialchars($lat, ENT_QUOTES, 'UTF-8'); ?>" />
    </li>
    <li>
        <label for="jcbtn_lng"><?php echo Text::_('COM_GEOFACTORY_BTN_PLG_LONGITUDE'); ?></label>
        <input type="text" id="jcbtn_lng" name="jcbtn_lng" value="<?php echo htmlspecialchars($lng, ENT_QUOTES, 'UTF-8'); ?>" />
    </li>
    <li>
        <label for="addcode"><?php echo Text::_('COM_GEOFACTORY_BTN_PLG_ADDCODE'); ?></label>
        <input type="checkbox" id="addcode" name="addcode" value="1" />
    </li>
</ul>

<?php echo $map; ?>

<div style="padding: 3px 3px 4px 6px;">
    <input type="hidden" id="idArt" value="<?php echo (int)$id; ?>"/>
    <button onclick="savePosition();"><?php echo Text::_('JSAVE'); ?></button>
    <!-- Nota: In Joomla 4 non c'è SqueezeBox di default -->
    <button onclick="if(window.parent.SqueezeBox){window.parent.SqueezeBox.close();}"><?php echo Text::_('JCANCEL'); ?></button>
</div>
