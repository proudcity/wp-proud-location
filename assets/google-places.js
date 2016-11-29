(function($) {
  var settings = window.proud_location,
      contact = window.proud_location_contact
      options = {
        componentRestrictions: { country: "US" },
        bounds: new google.maps.LatLngBounds(
            new google.maps.LatLng(settings.lat, settings.lng),
            new google.maps.LatLng(settings.lat, settings.lng))
      };
  var places, places1;
  google.maps.event.addDomListener(window, 'load', function () {
    places = new google.maps.places.Autocomplete(document.getElementById(settings.address_id), options);
    google.maps.event.addListener(places, 'place_changed', function() {
      return autocomplete_change(places, true)
    } );
    places1 = new google.maps.places.Autocomplete(document.getElementById('title'), options);
    google.maps.event.addListener(places1, 'place_changed', function() {
      return autocomplete_change(places1)
    } );
  });

  var autocomplete_change = function ( places, setTitle ) {
    var place = places.getPlace();
    var address = '';
    if (setTitle && place.name) {
      setField('title', place.name, true);
    }
    for (var i=0; i<place.address_components.length; i++) {
      value = place.address_components[i].short_name;
      switch (place.address_components[i].types[0]) {
        case 'street_number':
          address = value;
          break;
        case 'route':
          setField( settings.address_id, address + ' ' + value, true);
          break;
        case 'locality':
          setField( settings.city_id, value);
          break;
        case 'administrative_area_level_1':
          setField( settings.state_id, value);
          break;
        case 'postal_code':
          setField( settings.zip_id, value);
          break;
      };
    }
    if (title != undefined && title && place.types[0] != 'street_address') {
      setField( 'title', place.name, true );
    }
    setField( settings.lat_id, place.geometry.location.lat, true );
    setField( settings.lng_id, place.geometry.location.lng, true );
    setField( settings.website_id, place.website, true );
    setField( settings.email_id, place.email, true );
    setField( settings.phone_id, place.formatted_phone_number, true );
    if (place.opening_hours != undefined && place.opening_hours.weekday_text != undefined ) {
      setField( settings.hours_id, place.opening_hours.weekday_text.join("\r\n"));
    }
    return false;
  }

  var setField = function( id, value, overwrite ) {
    if ( ((overwrite != undefined && overwrite == true) || $('#'+id).val() == '') && value != undefined) {
      $('#'+id).val(value);
    }
  }

  // Cleanup
  //$('#-lat, #-lng').hide();
  $('#title').attr('placeholder', '');
  $('#postdivrich').appendTo('#location_description_meta_box');

  // The form would submit when Enter was pressed previously, which isn't
  // great for the location autocomplete
  $('#post').on('keyup keypress', function(e) {
    var keyCode = e.keyCode || e.which;
    if (keyCode === 13) { 
      e.preventDefault();
      return false;
    }
  });


})(jQuery);
