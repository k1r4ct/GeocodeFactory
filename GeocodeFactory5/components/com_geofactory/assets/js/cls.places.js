function searchPlaces(a,b){gpPlaces=new google.maps.places.PlacesService(a.map),listitemsscope=b,0==a.mode?gpPlaces.nearbySearch(a,function(b,c){drawPlaces(b,c,a,listitemsscope)}):1==a.mode&&gpPlaces.radarSearch(a,function(b,c){drawPlaces(b,c,a,listitemsscope)})}function drawPlaces(a,b,c,d){if(b==google.maps.places.PlacesServiceStatus.OK)for(var e=0,f=0;f<a.length;f++){if(c.max>0&&e==c.max)return;var g=new google.maps.Marker({position:a[f].geometry.location,icon:c.icon,title:a[f].name});numberofMarkers+=1,updateCounter(),g.setMap(c.map),d.push(g),e+=1,google.maps.event.addListener(g,"click",getDetails(a[f],g,c.map,c.imageWait,c.liste))}}function getDetails(a,b,c,d,e){return function(){gpPlaces.getDetails({reference:a.reference},showInfoWindow(b,c,d,e))}}function showInfoWindow(a,b,c,d){return function(e,f){if(iw&&iw.close(),iw=new google.maps.InfoWindow,iw.setContent(c),f==google.maps.places.PlacesServiceStatus.OK){var g;try{"undefined"!=typeof ActiveXObject?g=new ActiveXObject("Microsoft.XMLHTTP"):window.XMLHttpRequest&&(g=new XMLHttpRequest)}catch(h){}"function"==typeof onBubbleOpen&&onBubbleOpen(b,a);var i="",k="",l="";e.formatted_phone_number&&(i=e.formatted_phone_number),k=e.website?e.website:e.url,e.rating&&(l=e.rating);var m={name:e.name,icon:e.icon,url:e.url,website:k,address:e.vicinity,phone:i,rating:l},n=gf_sr+commonUrl+"&task=marker.bubblePl"+"&idL="+d+"&pt="+a.getPosition().lat()+","+a.getPosition().lng()+"&data="+JSON.stringify(m);g.open("GET",n,!0),document.getElementById("gf_debugmode_bubble")&&(document.getElementById("gf_debugmode_bubble").innerHTML="bubble link",document.getElementById("gf_debugmode_bubble").href=n),g.onreadystatechange=function(){if(4==g.readyState){var c=g.responseText;iw.setContent(c),iw.open(b,a)}},g.send(null)}}}