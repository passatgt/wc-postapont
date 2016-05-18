var ppapi = {};

jQuery(document).ready(function($){

	//ZipField
	if($('#shipping_postcode').val() != '') {
		var zipField = 'shipping_postcode';
	} else {
		var zipField = 'billing_postcode';
	}

	ppapi = {
		APIURL: 'https://www.postapont.hu/ppapi/',
		map: null,
		pplist: [],
		ppmarkerslist: null,
		onSelect: null,
		opts: {},
		symbols: {},
		infoWindow: {},
		markers: [],
		skipgroups: {},

		linkZipField: function(zipfield_id){
			$('#'+zipfield_id).blur( function(){
				if( ppapi.zipcode == $(this).val() ) return;
				ppapi.zipcode = $(this).val();
				ppapi.initZip(ppapi.zipcode);
			});
			$('#'+zipfield_id).keypress( function(e){
				var code = e.which;
				if(code==13){
					ppapi.zipcode = $(this).val();
					ppapi.initZip(ppapi.zipcode);
					$(this).blur();
					return false;
				}
			});
			ppapi.zipcode = $('#'+zipfield_id).val();
			ppapi.initZip(ppapi.zipcode);
		},

		apicall: function(method, data, success){
			$.ajax({
				crossDomain: true,
				dataType: 'jsonp',
				data: data,
				url: ppapi.APIURL+method,
				success: success,
				error: function(jqXHR, textStatus, errorThrown){
					alert('error: '+textStatus);
				}
			});
		},

		insertMap: function(field_id, opts){

			ppapi.setOpts(opts);
			ppapi.apicall('insertMap', null, function(data, textStatus, jqXHR ){
				$('#'+field_id).html(data);

				$('#pp-select-button').click(function(){
					var ppselectobj = $('#pp-select-postapont');
					if( !ppapi.pplist || ppselectobj.get(0).selectedIndex<0) return;
					ppapi.selectPP(ppapi.pplist[ppselectobj.get(0).selectedIndex]);
				});

				$('#pp-geoloc-button').click(function(){
					if (!navigator.geolocation) return;
					navigator.geolocation.getCurrentPosition( ppapi.initGeolocation, function(){
						alert('Nem sikerült lekédezni az Ön helyzetét.');
					});
				});

				$('#pp-select-postapont').change(function(){
					var item = ppapi.pplist[$(this).get(0).selectedIndex];
					ppapi.zoomtoPP(item);
					var marker = new google.maps.Marker({
						position: new google.maps.LatLng(item.lat, item.lon),
						data: item
					});
					ppapi.mapOpenMarkerInfo(marker);
				});

				ppapi.mapInitialize();
			});
		},

		initZip: function(zip){
			if(!zip) return;
			if(!ppapi.map) return;

			ppapi.apicall('geozip', {
				'zipCode': zip,
				}, function(data, textStatus, jqXHR ){

					if(data.type=='symbolzip'){
						ppapi.zipkrd = new google.maps.LatLng(data.lat, data.lon),
						ppapi.map.setCenter( ppapi.zipkrd );
						ppapi.reloadPP();

					} else if(data.type=='mapzip'){
						geocoder = new google.maps.Geocoder();
						geocoder.geocode( { 'address': zip+', Magyarország', 'region':'HU'}, function(results, status) {
							if (status == google.maps.GeocoderStatus.OK) {
								ppapi.zipkrd = results[0].geometry.location;
								ppapi.map.setCenter( ppapi.zipkrd );
								ppapi.reloadPP();
							} else {
								//alert('Geocode was not successful for the following reason: ' + status);
								alert('Érvénytelen irányítószám!');
							}
						});

					} else alert('Érvénytelen irányítószám!');

				}
			);

		},

		reloadPP: function(fitBounds){
			var skipgroups = Array();
			for(i in ppapi.skipgroups) if(ppapi.skipgroups[i]) skipgroups.push(i);
			ppapi.apicall('listPP', {
				'zipcode': ppapi.zipcode,
				'lat': ppapi.zipkrd.lat(),
				'lng': ppapi.zipkrd.lng(),
				'group': ppapi.opts.group,
				'skipgroups': skipgroups.join(',')
				}, function(data, textStatus, jqXHR ){
					ppapi.pplist = data;
					ppapi.displayPP(fitBounds);
				}
			);
		},

		mapInitialize: function(){
			google.maps.visualRefresh = true;
			var mapOptions = {
				backgroundColor: '#ffffff',
				mapTypeControl: true,
				mapTypeControlOptions: {style: google.maps.MapTypeControlStyle.DROPDOWN_MENU},
				scaleControl: true,
				zoom: 7,
				center: new google.maps.LatLng(47.2, 19.6),
				mapTypeId: google.maps.MapTypeId.ROADMAP
			};
			ppapi.map = new google.maps.Map(document.getElementById('pp-map-canvas'), mapOptions);

			ppapi.infoWindow = new google.maps.InfoWindow({});
			ppapi.symbols['10_posta'] = new google.maps.MarkerImage(ppapi.APIURL+'../static/img/ppapi/mapsymbol-posta.png',
					new google.maps.Size(25, 20), new google.maps.Point(0,0), new google.maps.Point(13, 20));
			ppapi.symbols['20_molkut'] = new google.maps.MarkerImage(ppapi.APIURL+'../static/img/ppapi/mapsymbol-molkut.png',
					new google.maps.Size(25, 20), new google.maps.Point(0,0), new google.maps.Point(13, 20));
			ppapi.symbols['30_csomagautomata'] = new google.maps.MarkerImage(ppapi.APIURL+'../static/img/ppapi/mapsymbol-csomagautomata.png',
					new google.maps.Size(20, 29), new google.maps.Point(0,0), new google.maps.Point(9, 29));
			ppapi.symbols['40_postapontplusz'] = new google.maps.MarkerImage(ppapi.APIURL+'../static/img/ppapi/mapsymbol-ppplusz.png',
					new google.maps.Size(25, 20), new google.maps.Point(0,0), new google.maps.Point(13, 20));
			ppapi.symbols['50_coop'] = new google.maps.MarkerImage(ppapi.APIURL+'../static/img/ppapi/mapsymbol-coop.png',
					new google.maps.Size(25, 9), new google.maps.Point(0,0), new google.maps.Point(13, 6));

			var skipgroups = Array();
			for(i in ppapi.skipgroups) if(ppapi.skipgroups[i]) skipgroups.push(i);
			ppapi.apicall('listPPMarkers', {
				'group': ppapi.opts.group,
				'skipgroups': skipgroups.join(',')
				}, function(data, textStatus, jqXHR ){
					ppapi.ppmarkerslist = data;
					ppapi.displayPPMarkers();
				}
			);

			ppapi.zipkrd = ppapi.map.getCenter();
			if( ppapi.pplist.length == 0 ) ppapi.initZip(ppapi.zipcode);
			//ppapi.reloadPP(false);
		},

		setOpts: function(opts){
			if(!opts) opts = {};
			ppapi.opts = opts;
			if(!ppapi.opts.group) ppapi.opts.group = '';
		},

		displayPP: function(fitBounds){
			if( fitBounds == undefined ) fitBounds = true;
			var ppselectobj = $('#pp-select-postapont');
			ppselectobj.empty();

			var lat_min = 90, lat_max = -90, lon_min = 180, lon_max = -180, n = 0;
			if(ppapi.pplist) $.each(ppapi.pplist, function(key, item) {
				ppselectobj.append($("<option />").val(item.id).text( item.name+' ('+item.zip+' '+item.county+', '+item.address+')' ));
				if( n==0 || item.distance<30 ) {
					lat_min = Math.min(lat_min, item.lat);
					lon_min = Math.min(lon_min, item.lon);
					lat_max = Math.max(lat_max, item.lat);
					lon_max = Math.max(lon_max, item.lon);
					n++;
				}
			});

			if( fitBounds && n>0 ){
				var southWest = new google.maps.LatLng(lat_min,lon_min);
				var northEast = new google.maps.LatLng(lat_max,lon_max);
				var bounds = new google.maps.LatLngBounds(southWest,northEast);
				if( n==1 ){
					ppapi.map.setCenter( southWest );
					ppapi.map.setZoom(13);
				} else ppapi.map.fitBounds( bounds );
			}

		},

		displayPPMarkers: function(){
			for (var i = 0; i < ppapi.markers.length; i++) ppapi.markers[i].setMap(null);
			delete(ppapi.markers);
			ppapi.markers = [];
			if (!ppapi.ppmarkerslist) return;

			$.each(ppapi.ppmarkerslist, function(key, item) {
				if ( ppapi.skipgroups[item.group] ) return;

				var marker = new google.maps.Marker({
					position: new google.maps.LatLng(item.lat, item.lon),
					//map: ppapi.map,
					flat: true,
					icon: ppapi.symbols[item.group],
					title: item.name,
					data: ppapi.ppmarkerslist[key],
				});
				ppapi.markers.push(marker);
				marker.data.index = key;

				(function(marker) {
					google.maps.event.addListener(marker, 'click', function(){ppapi.mapOpenMarkerInfo(marker);});
				}(marker));
			});

			markerClusterer = null;
			var mcOptions = {
				maxZoom: 13,
				gridSize: 50,
				imagePath: ppapi.APIURL+'../static/img/ppapi/clusterer-m'
			};
			markerClusterer = new MarkerClusterer(ppapi.map, [], mcOptions);
			for(var i in markerClusterer.styles_) markerClusterer.styles_[i].textColor = 'white';
			markerClusterer.addMarkers(ppapi.markers, true);
		},

		mapOpenMarkerInfo: function(marker){
			var html = '<div class="pp-map-info" style="overflow: hidden;">'
				+'<b>' + marker.data.name + '</b><br />'
				+'Cím: ' + marker.data.zip + ' ' + marker.data.county + ',<br />' + marker.data.address + '<br />'
				+ ((marker.data.phone) ?'Telefon: ' + marker.data.phone + '<br />' :'')
				+'<a href="#" style="color:#00f;" onclick="ppapi.mapMarkerSelect('+JSON.stringify(marker.data).replace(/\"/g,'\'')+'); return false;">Kiválaszt</a>'
				+'<\/div>';
			ppapi.infoWindow.setContent(html);
			ppapi.infoWindow.setPosition(marker.position);
			ppapi.infoWindow.open(ppapi.map);
		},

		mapMarkerSelect: function(item){
			var ppselectobj = $('#pp-select-postapont');
			var optobj = $('option[value="' + item.id + '"]', ppselectobj);
			if(optobj.length){
				optobj.attr('selected', 'selected');
			} else {
				ppapi.pplist.push(item);
				ppselectobj.append($("<option />").val(item.id).text( item.name+' ('+item.zip+' '+item.county+', '+item.address+')' ).attr('selected', 'selected'));
			}
			return ppapi.selectPP(item);
		},

		selectPP: function(data){
			ppapi.infoWindow.close();
			if(ppapi.onSelect) ppapi.onSelect(data);
			return false;
		},

		zoomtoPP: function(data){
			ppapi.infoWindow.close();
			ppapi.map.panTo( new google.maps.LatLng(data.lat, data.lon) );
			if( ppapi.map.getZoom() < 14 ) ppapi.map.setZoom(14);
		},

		setMarkers: function(layer, display){
	//		if(layer == '20_molkut') layer = '40_postapontplusz';
			if(display == undefined) display = true;
			ppapi.skipgroups[layer] = (display) ?false :true;
			ppapi.displayPPMarkers();
			ppapi.initZip(ppapi.zipcode);
		},

		initGeolocation: function(position){
			ppapi.zipkrd = new google.maps.LatLng(position.coords.latitude, position.coords.longitude),
			ppapi.map.setCenter( ppapi.zipkrd );
			ppapi.reloadPP();
		}

	};

	$( 'body' ).bind( 'updated_checkout', function() {

		if($('#postapontvalasztoapi').length) {

			//Check for disabled categories
			if(posta_pont_options.disabled_categories) {
				$.each( posta_pont_options.disabled_categories, function( index, value ){
					ppapi.setMarkers(value, false);
				});
			}

			ppapi.linkZipField(zipField); //<-- A megrendelő form input elemének a megjelölése (beállítása a kiválasztó számára)
			ppapi.insertMap('postapontvalasztoapi'); //<-- PostaPont választó API beillesztése ( ilyen azonosítóval rendelkező DOM objektumba)
			ppapi.onSelect = function(data){ //<-- Postapont kiválasztásra bekövetkező esemény lekötése
				//A kiválasztott PostaPont adatainak visszaírása a megrendelő form rejtett mezőjébe.
				$('#wc_selected_postapont').val( data['name'] + '|' +data['zip'] +'|'+ data['county'] +'|'+ data['address'] );

				//Adatkiírás
				$('#valasztott_postapont').show().find('p').html(data['name'] + '<br>' +data['zip'] +'<br>'+ data['county'] +'<br>'+ data['address']);
			}

		}

	});

});
