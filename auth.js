function openWindow( url, title, width, height ) {
	var left = ( screen.width - width ) / 2,
		top = ( screen.height - height ) / 2;
	return window.open( url, title, 'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=no, copyhistory=no, width=' + width + ', height=' + height + ', top=' + top + ', left=' + left );
}

function unlink( profile ) {
	jQuery.ajax( {
		url: '/Special:SocialLogin',
		data: { action: 'unlink', profile: profile },
		success: function ( response ) {
			if ( response === 'yes' ) {
				jQuery( '#' + profile.replace( '@', '_' ).replace( '.', '_' ) ).remove();
			} else {
				window.alert( 'Не удалось отсоединить профиль социальной сети.' );
			}
		}
	} );
}

function gup( url, name ) {
	name = name.replace( /[\[]/, '\\\[' ).replace( /[\]]/, '\\\]' );
	var regexS = '[\\#&]' + name + '=([^&#]*)',
		regex = new RegExp( regexS ),
		results = regex.exec( url );
	return ( results === null ) ? '' : results[ 1 ];
}

function tryLogin( data, cb ) {
	var formText = '<form action="" method="post"><input type="hidden" name="action" value="login" />';
	jQuery.each( data, function ( key, value ) {
		formText += '<input type="hidden" name="' + key + '" value="' + value + '" />';
	} );
	formText += '</form>';
	var form = jQuery( formText );
	jQuery( 'body' ).append( form );
	jQuery( form ).trigger( 'submit' );
}

function login( url, cb ) {
	var win = openWindow( url, 'sl', 620, 370 ),
		pollTimer = window.setInterval( function () {
			try {
				if ( win.document.URL.indexOf( window.document.location.host ) >= 0 ) {
					window.clearInterval( pollTimer );
					var url = win.document.URL,
						code = gup( url, 'code' );
					win.close();
					cb( code );
				}
			} catch ( e ) {
			}
		}, 50 );
}
