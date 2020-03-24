<?php
/*
Plugin Name: Civi Member Sync Custom
Plugin URI: https://fullworks.net
Description: Filters Contacts
Version: 1.0
Author: alan
Author URI: https://fullworks.net
License: GPL2
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_filter( 'civi_wp_member_sync_contact_retrieved', function ( $contact_data ) {

// Find the Head of Household contact ID   - this is a custom relationship with ID of 7.

	$relationship = civicrm_api( 'Relationship', 'get', array(
		'version'              => 3,
		'sequential'           => 1,
		'return'               => [ "contact_id_a" ],
		'contact_id_b'         => $contact_data['contact_id'],
		'relationship_type_id' => 7,
		'is_active'            => 1,
	) );

// Now switch the contact data if it exists
	if ( isset ( $relationship['values'][0] ) ) {
		$params = array(
			'version'    => 3,
			'contact_id' => $relationship['values'][0]['contact_id_a'],
		);

		// Use API.
		$contact_data_head = civicrm_api( 'contact', 'get', $params );

		if ( isset( $contact_data_head['id'] ) ) {
			if ( isset( $contact_data_head['values'][ $contact_data_head['id'] ]['email'] ) ) {
				$contact_data['email'] = $contact_data_head['values'][ $contact_data_head['id'] ]['email'];
			};
			if ( isset( $contact_data_head['values'][ $contact_data_head['id'] ]['sort_name'] ) ) {
				$contact_data['sort_name'] = $contact_data_head['values'][ $contact_data_head['id'] ]['sort_name'];
			};
			if ( isset( $contact_data_head['values'][ $contact_data_head['id'] ]['display_name'] ) ) {
				$contact_data['display_name'] = $contact_data_head['values'][ $contact_data_head['id'] ]['display_name'];
			};
		}
	};

	return $contact_data;
},
	10,
	1
);

