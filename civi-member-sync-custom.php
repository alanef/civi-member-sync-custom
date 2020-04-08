<?php
/**
 * @copyright (c) 2020.
 * @author            Alan Fuller (support@fullworks)
 * @licence           GPL V3 https://www.gnu.org/licenses/gpl-3.0.en.html
 * @link                  https://fullworks.net
 *
 * This file is part of  a Fullworks plugin.
 *
 *   This plugin is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     (at your option) any later version.
 *
 *     This plugin is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 *
 *     You should have received a copy of the GNU General Public License
 *     along with  this plugin.  https://www.gnu.org/licenses/gpl-2.0.en.html
 *
 * Plugin Name: Civi Member Sync Custom
 * Plugin URI: https://fullworks.net
 * Description: Filters Contacts for Member Sync and also sync emails one way from CiviCrm to WP
 * Version: 2.2
 *
 * Author: alan
 * Author URI: https://fullworks.net
 * License: GPL2
 *
 *
 * This plugin extends CiviCRM WordPress Member Sync by Christian Wach
 * to cater for a membership structure where the member is household,
 * but the login is via the Head of Household ( custom relationship id = 7 )
 *
 * It does this by filtering the contact data by matching up the relationship and grabbing the email
 *
 * If no email exists it generates a random email so later email changes can be applied
 *
 * It also does a one way syncronisation from CiviCRM to WordPress for email changes of releationship changes in CiviCRM
 * using the custom relationship
 *
 * There is no error checking so if CiviCRM is not loaded things will simple fail, but us driven off CivCRM filters so a low risk here
 *
 * With thanks to Christian Wach for his tips and code examples
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_filter( /**
 * Change the contact data to use a different email
 *
 * @param $contact_data
 *
 * @return mixed
 */
	'civi_wp_member_sync_contact_retrieved',
	function ( $contact_data ) {
// Find the Head of Household contact ID   - this is a custom relationship with ID of 7.
		$relationship = civicrm_api( 'Relationship', 'get', array(
			'version'              => 3,
			'sequential'           => 1,
			'return'               => [ "contact_id_a" ],
			'contact_id_b'         => $contact_data['contact_id'],
			'relationship_type_id' => 7,
			'is_active'            => 1,
		) );
		$email_found  = false;
// Now switch the contact data if it exists.
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
					$email_found           = true;
				};
			}
		};

		if ( false === $email_found ) {
			// generate an email and make sure it does not exist.
			// change the @fullworks.net to your own domain, the email is never used but if you use a domain that you do not control someone in theory could add a catchall email.
			do {
				$contact_data['email'] = md5( time() ) . '@fullworks.net';
			} while ( false !== email_exists( $contact_data['email'] ) );
		}

		return $contact_data;
	},
	10,
	1
);

add_action( /**
 * Process email changes and apply to the correct WP user
 *
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $objectRef
 */
	'civicrm_post',
	function ( $op, $objectName, $objectId, $objectRef ) {
		// only edits and creates
		if ( $op != 'edit' && $op != 'create' ) {
			return;
		}
		// Only email
		if ( $objectName != 'Email' ) {
			return;
		}
		// Check if we have a Contact email.
		if ( ! isset( $objectRef->email ) ) {
			return;
		}
		// Find the Household based on  Head of Household contact ID   - this is a custom relationship with ID of 7.
		$relationship = civicrm_api( 'Relationship', 'get', array(
			'version'              => 3,
			'sequential'           => 1,
			'return'               => [ "contact_id_b" ],
			'contact_id_a'         => $objectRef->contact_id,
			'relationship_type_id' => 7,
			'is_active'            => 1,
		) );
		// no relationship, bail.
		if ( ! isset ( $relationship['values'][0]['contact_id_b'] ) ) {
			return;
		}
		$user_id = CRM_Core_BAO_UFMatch::getUFId(
			$relationship['values'][0]['contact_id_b']
		);
		if ( null === $user_id ) {
			return;
		}
		$user = get_userdata( $user_id );
		if ( false == $user ) {
			return;
		}
		$user->data->user_email = $objectRef->email;
		$result                 = wp_update_user( $user );
		if ( is_wp_error( $result ) ) {
			error_log( 'Error syncing user: ' . $result->get_error_message() . print_r( $user, true ) );

			return;
		}
	},
	10,
	4
);

add_action( /**
 * Process relationship changes to get correct email
 *
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $objectRef
 */
	'civicrm_post',
	function ( $op, $objectName, $objectId, $objectRef ) {
		// only edit & creates
		if ( $op != 'edit' && $op != 'create' ) {
			return;
		}
		// Only relationships
		if ( $objectName != 'Relationship' ) {
			return;
		}

		if ( 7 != $objectRef->relationship_type_id || 1 != $objectRef->is_active ) {
			return;
		}
		if ( ! isset ( $objectRef->contact_id_a ) || ! isset ( $objectRef->contact_id_b ) ) {
			return;
		}
		// get the email from the Individual ( Head of House ) contact_id_a
		$individual = civicrm_api( 'Contact', 'get', array(
			'version'    => 3,
			'sequential' => 1,
			'return'     => [ "email" ],
			'contact_id' => $objectRef->contact_id_a,
		) );
		if ( ! isset ( $individual['values'][0]['email'] ) ) {
			return;
		}
		if ( empty ( $individual['values'][0]['email'] ) ) {
			return;
		}

		$user_id = CRM_Core_BAO_UFMatch::getUFId(
			$objectRef->contact_id_b
		);
		if ( null === $user_id ) {
			return;
		}
		$user = get_userdata( $user_id );
		if ( false == $user ) {
			return;
		}
		$user->data->user_email = $individual['values'][0]['email'];
		$result                 = wp_update_user( $user );
		if ( is_wp_error( $result ) ) {
			error_log( 'Error syncing user: ' . $result->get_error_message() . print_r( $user, true ) );

			return;
		}
	},
	10,
	4
);

