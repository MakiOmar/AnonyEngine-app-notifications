jQuery( document ).ready(
	function ($) {
		'use strict';

		/**
		 * All of the code for your public-facing JavaScript source
		 * should reside in this file.
		 *
		 * Note: It has been assumed you will write jQuery code here, so the
		 * $ function reference has been prepared for usage within the scope
		 * of this function.
		 *
		 * This enables you to define handlers, for when the DOM is ready:
		 *
		 * $(function() {
		 *
		 * });
		 *
		 * When the window is loaded:
		 *
		 * $( window ).load(function() {
		 *
		 * });
		 *
		 * ...and/or other possibilities.
		 *
		 * Ideally, it is not considered best practise to attach more than a
		 * single DOM-ready or window-load handler for a particular page.
		 * Although scripts in the WordPress core, Plugins and Themes may be
		 * practising this, we should strive to set a better example in our own work.
		 */
		var soundPlayed = false;
		function playSound(url) {
				const audio = new Audio( url );
				audio.play().catch(
					function (error) {
						console.log( 'Audio play prevented:', error );
					}
				);
		}
		// Update browser tab title when a notification is received.
		function updateTabTitleWithNotification() {
			var originalTitle     = '';
			var notificationTitle = "New Notification Received!";

			// Update tab title with the notification title.
			document.title = notificationTitle;

			// Restore the original tab title after a certain duration (e.g., 2 seconds).
			setInterval(
				function () {
					if ( document.title === notificationTitle ) {
								document.title = originalTitle;
					} else {
						document.title = notificationTitle;
					}
				},
				2000
			);
		}
		// Function to retrieve and display notifications
		function retrieveNotifications() {
			var oldNotfCount;
			if ( $( '#anotf-old-count' ).length > 0 ) {
				oldNotfCount = $( '#anotf-old-count' ).val();
			} else {
				oldNotfCount = 0;
			}
			$.ajax(
				{
					url: anotf_ajax_object.ajaxUrl,
					type: 'POST',
					data: {
						action       : 'anotf_retrieve_notifications',
						oldNotfCount : oldNotfCount,
						_ajax_nonce  : anotf_ajax_object.nonce // Include the nonce for security
					},
					success: function (response) {
						if ( parseInt( oldNotfCount ) < parseInt( response.data.count )  ) {
							$( '.anotf-notification-status' ).addClass( 'anotf-has-new' );
							if ( ! soundPlayed ) {
								$( document ).on(
									'click',
									function () {
										if ( ! soundPlayed) {
											playSound( anotf_ajax_object.sound );
											soundPlayed = true;
										}
									}
								);
							}
							soundPlayed = true;
							updateTabTitleWithNotification();
						}
						var notificationsList = $( '.anotf-notifications-list ul' );
						notificationsList.empty();
						notificationsList.html( response.data.notifications );
					},
					error: function (error) {
						console.log( 'Error:', error );
					}
				}
			);
		}

		// AJAX request to mark a notification as read
		function markNotificationRead(notificationId) {
				$.ajax(
					{
						url: anotf_ajax_object.ajaxUrl,
						type: 'POST',
						data: {
							action: 'anotf_mark_notification_read',
							notification_id: notificationId,
							_ajax_nonce: anotf_ajax_object.nonce // Include the nonce for security
						},
						success: function (response) {
							// Update the UI to indicate the notification has been read
							$( 'li[data-notification-id="' + notificationId + '"]' ).addClass( 'anotf-notification-read' );
						},
						error: function (error) {
							console.log( 'Error:', error );
						}
					}
				);
		}
		if ( $( '.anotf-has-new' ).length > 0 ) {
			$( document ).on(
				'click',
				function () {
					playSound( anotf_ajax_object.sound );
				}
			);
			updateTabTitleWithNotification();
		}
		// Show/hide the notifications list on hover
		$( '.anotf-notification-bell' ).on(
			'click',
			function () {
				$( '.anotf-notification-bell-container' ).toggleClass( 'show-notififcations' );
				if ( $( '.anotf-notification-bell-container' ).hasClass( 'show-notififcations' ) ) {
					$.ajax(
						{
							url: anotf_ajax_object.ajaxUrl,
							type: 'POST',
							data: {
								action       : 'anotf_notifications_status',
								_ajax_nonce  : anotf_ajax_object.nonce // Include the nonce for security
							},
							success: function (response) {
								if ( response.resp ) {
									$( '.anotf-notification-status' ).removeClass( 'anotf-has-new' );
								}

							},
							error: function (error) {
								console.log( 'Error:', error );
							}
						}
					);
				}
			}
		);

		// Set interval to update notifications every 5 seconds
		setInterval(
			function () {
				//retrieveNotifications();
			},
			10000
		);
		$( document ).on(
			'click',
			'.anotf-notifications-list li a',
			function ( e ) {
				e.preventDefault();
				var newLoc = $( this ).attr( 'href' );
				setTimeout(
					function () {
						window.location.href = newLoc;
					},
					1000
				);
			}
		);
		// Click event for marking a notification as read
		$( document ).on(
			'click',
			'.anotf-notifications-list li',
			function () {
				var notificationId = $( this ).data( 'notification-id' );
				markNotificationRead( notificationId );
			}
		);
	}
);
