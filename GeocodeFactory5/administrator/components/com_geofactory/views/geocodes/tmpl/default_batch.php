<?php
/**
 * @name        Geocode Factory
 * @package     geoFactory
 * @copyright   Copyright © 2013 - All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @author      Cédric Pelloquin aka Rick <info@myJoom.com>
 * @website     www.myJoom.com
 * @update      Daniele Bellante
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

// Aggiungiamo il percorso per eventuali override degli helper HTML
HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');

// In Joomla 4 si consiglia di usare i comportamenti Bootstrap per i tooltip
HTMLHelper::_('bootstrap.tooltip');
// Multiselezione rimane invariata
HTMLHelper::_('behavior.multiselect');
// Il vecchio comportamento modal non esiste in Joomla 4.
// Se necessario, puoi abilitare il comportamento dei modali Bootstrap (ad es. HTMLHelper::_('bootstrap.modal');)
// HTMLHelper::_('behavior.modal'); // rimosso
// Carica jQuery
HTMLHelper::_('jquery.framework');

$config   = ComponentHelper::getParams('com_geofactory');
$client   = (int) $config->get('geocodeClient', 0);
$delayRaw = (int) $config->get('iPauseGeo', 2000000);
$delay    = $delayRaw / 1000; // delay in secondi (il valore originale era in microsecondi)
$jobEnd   = "<a class='btn btn-primary btn-large' href='" . 
            Route::_('index.php?option=com_geofactory&view=geocodes&assign=' . $this->assign . "&typeliste=" . $this->type) . 
            "'>" . Text::_('COM_GEOFACTORY_GEOCODE_DONE') . "</a>";

$total  = 0;
$idsToGc = '';
if (is_array($this->idsToGc) && count($this->idsToGc) > 0) {
    $idsToGc = implode(',', $this->idsToGc);
    $total   = count($this->idsToGc);
}

$doc = Factory::getDocument();
$doc->addStyleDeclaration('.map-canvas img { max-width: none !important; }');

$delayClient = ($client) ? 500 : 0; // se client side
$ggApikey    = trim($config->get('ggApikey'));
$ggApikeyQ   = (strlen($ggApikey) > 4) ? "&key=" . $ggApikey : "";
?>
<!-- Includiamo l'API di Google Maps -->
<script src="https://maps.googleapis.com/maps/api/js?<?php echo $ggApikeyQ; ?>"></script>
<script type="text/javascript">
jQuery(document).ready(function(){
    var needStop = false;
    jQuery('#button_stop').click(function(){
        needStop = true;
    });

    // Avvia l'inizializzazione della mappa e il job di geocodifica
    initialize();
    geocodeJob(-1);

    function geocodeJob(item){
        var toGc = [<?php echo $idsToGc; ?>];
        item = parseInt(item) + 1;

        setTimeout(function(){
            <?php if ($client) : ?>
                geocodeItemClient(item, toGc[item]);
            <?php else : ?>
                geocodeItemServer(item, toGc[item]);
            <?php endif; ?>
        }, <?php echo (int)$delay; ?> + item + <?php echo (int)$delayClient; ?>);
    }

    function geocodeItemClient(item, curid){
        if (needStop){
            jQuery('#geocodeEnd').html("<?php echo $jobEnd; ?>");
            return;
        }
        var total = parseInt(jQuery('#total').val());
        if (item >= total){
            jQuery('#geocodeEnd').html("<?php echo $jobEnd; ?>");
            return;
        }
        var urlAx  = 'index.php?option=com_geofactory&task=geocodes.getcurrentitemaddressraw';
        var arData = {
            'cur': item,
            'curId': curid,
            'total': total,
            'type': jQuery('#type').val(),
            'assign': jQuery('#assign').val()
        };
        jQuery('#currentIdx').val(item);

        jQuery.ajax({
            url: urlAx,
            data: arData,
            success: function(data){
                var defPos = new google.maps.LatLng(34, -41);
                if (data.length > 2){
                    var geocoder = new google.maps.Geocoder();
                    geocoder.geocode({'address': data}, function(results, status){
                        if(status == google.maps.GeocoderStatus.OK){
                            jQuery('#geocodeLog').html('Geocode ok');
                            var pos = results[0].geometry.location;
                            drawGeocodeResult(defPos, pos);

                            // Salva le coordinate
                            var urlAx2 = 'index.php?option=com_geofactory&task=geocodes.axsavecoord';
                            arData['savlat'] = pos.lat();
                            arData['savlng'] = pos.lng();
                            arData['savMsg'] = 'Save';
                            arData['adresse'] = data;

                            jQuery.ajax({
                                url: urlAx2,
                                data: arData,
                                success: function(resp){
                                    jQuery('#geocodeLog').html(resp);
                                }
                            });
                        }
                    });
                }
                geocodeJob(item);
            }
        });
    }

    function geocodeItemServer(item, curid){
        if (needStop){
            jQuery('#geocodeEnd').html("<?php echo $jobEnd; ?>");
            return;
        }
        var total = parseInt(jQuery('#total').val());
        if (item >= total){
            jQuery('#geocodeEnd').html("<?php echo $jobEnd; ?>");
            return;
        }
        var urlAx = 'index.php?option=com_geofactory&task=geocodes.geocodecurrentitem';
        var arData = {
            'cur': item,
            'curId': curid,
            'total': total,
            'type': jQuery('#type').val(),
            'assign': jQuery('#assign').val()
        };
        jQuery('#currentIdx').val(item);

        jQuery.ajax({
            url: urlAx,
            data: arData,
            success: function(data){
                var defPos = new google.maps.LatLng(34, -41);
                var htmlRes = 'Unknown Ajax Error';
                var splitted = data.split('#-@');
                if (splitted.length > 0){
                    htmlRes = splitted[0];
                }
                if (splitted.length > 2){
                    var lat = parseFloat(splitted[1]);
                    var lng = parseFloat(splitted[2]);
                    var pos = defPos;
                    if (lat !== 255){
                        pos = new google.maps.LatLng(lat, lng);
                        drawGeocodeResult(defPos, pos);
                    }
                }
                jQuery('#geocodeLog').html(htmlRes);
                geocodeJob(item);
            }
        });
    }

    function drawGeocodeResult(defPos, pos){
        if (!pos.equals(defPos)){
            var points = [defPos, pos];
            var pline = new google.maps.Polyline({
                path: points,
                geodesic: true,
                strokeColor: '#FF0000',
                strokeOpacity: 1.0,
                strokeWeight: 1
            });
            pline.setMap(map);
        }
        map.panTo(pos);
        var marker = new google.maps.Marker({
            map: map,
            draggable: false,
            animation: google.maps.Animation.DROP,
            position: pos
        });
    }
});

// Definiamo la mappa in una funzione globale "initialize"
var map;
function initialize(){
    var myLatlng = new google.maps.LatLng(34, -41);
    var mapOptions = {
        zoom: 4,
        center: myLatlng
    };
    map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);

    var origin = new google.maps.Marker({
        position: myLatlng,
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 10
        },
        draggable: false,
        map: map
    });
}
</script>

<h2><?php echo Text::_('COM_GEOFACTORY_GEOCODE_PROCESS'); ?></h2>

<form action="" method="post" name="adminForm" id="markerset-form">
    <input type="hidden" name="currentIdx" id="currentIdx" value="1">
    <input type="hidden" name="total" id="total" value="<?php echo (int)$total; ?>">
    <input type="hidden" name="type" id="type" value="<?php echo $this->type; ?>">
    <input type="hidden" name="assign" id="assign" value="<?php echo $this->assign; ?>">
    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<div style="max-width:100%;height:500px;" id="map-canvas" class="map-canvas"></div>

<div id="geocodeLog"></div>
<div id="geocodeEnd"></div>

<input type="button" id="button_stop" name="button_stop" value="Stop !">
