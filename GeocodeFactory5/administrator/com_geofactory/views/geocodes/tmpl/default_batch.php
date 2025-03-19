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
use Joomla\CMS\Router\Route;

// Aggiungiamo il percorso per eventuali override degli helper HTML
HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers/html');

// Tooltip e framework Bootstrap
HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('bootstrap.framework');

// Multiselezione rimane invariata
HTMLHelper::_('behavior.multiselect');

$config   = ComponentHelper::getParams('com_geofactory');
$client   = (int) $config->get('geocodeClient', 0);
$delayRaw = (int) $config->get('iPauseGeo', 2000000);
$delay    = $delayRaw / 1000; // delay in secondi (il valore originale era in microsecondi)
$jobEnd   = "<a class='btn btn-primary btn-lg' href='" . 
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
document.addEventListener('DOMContentLoaded', function() {
    let needStop = false;
    
    document.getElementById('button_stop').addEventListener('click', function() {
        needStop = true;
    });

    // Avvia l'inizializzazione della mappa e il job di geocodifica
    initialize();
    geocodeJob(-1);

    function geocodeJob(item) {
        const toGc = [<?php echo $idsToGc; ?>];
        item = parseInt(item) + 1;

        setTimeout(function() {
            <?php if ($client) : ?>
                geocodeItemClient(item, toGc[item]);
            <?php else : ?>
                geocodeItemServer(item, toGc[item]);
            <?php endif; ?>
        }, <?php echo (int)$delay; ?> + item + <?php echo (int)$delayClient; ?>);
    }

    function geocodeItemClient(item, curid) {
        if (needStop) {
            document.getElementById('geocodeEnd').innerHTML = "<?php echo $jobEnd; ?>";
            return;
        }
        
        const total = parseInt(document.getElementById('total').value);
        
        if (item >= total) {
            document.getElementById('geocodeEnd').innerHTML = "<?php echo $jobEnd; ?>";
            return;
        }
        
        const urlAx = 'index.php?option=com_geofactory&task=geocodes.getcurrentitemaddressraw';
        const arData = {
            'cur': item,
            'curId': curid,
            'total': total,
            'type': document.getElementById('type').value,
            'assign': document.getElementById('assign').value
        };
        
        document.getElementById('currentIdx').value = item;

        fetch(urlAx + '&' + new URLSearchParams(arData).toString())
            .then(response => response.text())
            .then(data => {
                const defPos = new google.maps.LatLng(34, -41);
                
                if (data.length > 2) {
                    const geocoder = new google.maps.Geocoder();
                    
                    geocoder.geocode({'address': data}, function(results, status) {
                        if (status == google.maps.GeocoderStatus.OK) {
                            document.getElementById('geocodeLog').innerHTML = 'Geocode ok';
                            const pos = results[0].geometry.location;
                            drawGeocodeResult(defPos, pos);

                            // Salva le coordinate
                            const urlAx2 = 'index.php?option=com_geofactory&task=geocodes.axsavecoord';
                            arData['savlat'] = pos.lat();
                            arData['savlng'] = pos.lng();
                            arData['savMsg'] = 'Save';
                            arData['adresse'] = data;

                            fetch(urlAx2 + '&' + new URLSearchParams(arData).toString())
                                .then(response => response.text())
                                .then(resp => {
                                    document.getElementById('geocodeLog').innerHTML = resp;
                                });
                        }
                    });
                }
                
                geocodeJob(item);
            });
    }

    function geocodeItemServer(item, curid) {
        if (needStop) {
            document.getElementById('geocodeEnd').innerHTML = "<?php echo $jobEnd; ?>";
            return;
        }
        
        const total = parseInt(document.getElementById('total').value);
        
        if (item >= total) {
            document.getElementById('geocodeEnd').innerHTML = "<?php echo $jobEnd; ?>";
            return;
        }
        
        const urlAx = 'index.php?option=com_geofactory&task=geocodes.geocodecurrentitem';
        const arData = {
            'cur': item,
            'curId': curid,
            'total': total,
            'type': document.getElementById('type').value,
            'assign': document.getElementById('assign').value
        };
        
        document.getElementById('currentIdx').value = item;

        fetch(urlAx + '&' + new URLSearchParams(arData).toString())
            .then(response => response.text())
            .then(data => {
                const defPos = new google.maps.LatLng(34, -41);
                let htmlRes = 'Unknown Ajax Error';
                const splitted = data.split('#-@');
                
                if (splitted.length > 0) {
                    htmlRes = splitted[0];
                }
                
                if (splitted.length > 2) {
                    const lat = parseFloat(splitted[1]);
                    const lng = parseFloat(splitted[2]);
                    let pos = defPos;
                    
                    if (lat !== 255) {
                        pos = new google.maps.LatLng(lat, lng);
                        drawGeocodeResult(defPos, pos);
                    }
                }
                
                document.getElementById('geocodeLog').innerHTML = htmlRes;
                geocodeJob(item);
            });
    }

    function drawGeocodeResult(defPos, pos) {
        if (!pos.equals(defPos)) {
            const points = [defPos, pos];
            const pline = new google.maps.Polyline({
                path: points,
                geodesic: true,
                strokeColor: '#FF0000',
                strokeOpacity: 1.0,
                strokeWeight: 1
            });
            
            pline.setMap(map);
        }
        
        map.panTo(pos);
        
        const marker = new google.maps.Marker({
            map: map,
            draggable: false,
            animation: google.maps.Animation.DROP,
            position: pos
        });
    }
});

// Definiamo la mappa in una funzione globale "initialize"
let map;

function initialize() {
    const myLatlng = new google.maps.LatLng(34, -41);
    const mapOptions = {
        zoom: 4,
        center: myLatlng
    };
    
    map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);

    const origin = new google.maps.Marker({
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

<div class="container-fluid">
    <h2><?php echo Text::_('COM_GEOFACTORY_GEOCODE_PROCESS'); ?></h2>

    <form action="" method="post" name="adminForm" id="markerset-form">
        <input type="hidden" name="currentIdx" id="currentIdx" value="1">
        <input type="hidden" name="total" id="total" value="<?php echo (int)$total; ?>">
        <input type="hidden" name="type" id="type" value="<?php echo $this->type; ?>">
        <input type="hidden" name="assign" id="assign" value="<?php echo $this->assign; ?>">
        <input type="hidden" name="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

    <div class="row">
        <div class="col-12">
            <div id="map-canvas" class="map-canvas" style="width:100%; height:500px;"></div>
        </div>
    </div>

    <div class="mt-3">
        <div id="geocodeLog" class="alert alert-info"></div>
        <div id="geocodeEnd"></div>

        <button type="button" id="button_stop" name="button_stop" class="btn btn-danger">Stop !</button>
    </div>
</div>