<?php
/**
 * Contains functions related to Invoicing plugin.
 *
 * @since 1.0.0
 * @package Invoicing
 */
 
// MUST have WordPress.
if ( !defined( 'WPINC' ) ) {
    exit( 'Do NOT access this file directly: ' . basename( __FILE__ ) );
}


function wpinv_get_default_country() {
	$country = wpinv_get_option( 'default_country', 'UK' );

	return apply_filters( 'wpinv_default_country', $country );
}

/**
 * Sanitizes a country code.
 * 
 * @param string $country The country code to sanitize
 * @return array
 */
function wpinv_sanitize_country( $country ) {

	// Enure the country is specified
    if ( empty( $country ) ) {
        $country = wpinv_get_default_country();
    }
    return trim( wpinv_utf8_strtoupper( $country ) );

}

function wpinv_is_base_country( $country ) {
    $base_country = wpinv_get_default_country();
    
    if ( $base_country === 'UK' ) {
        $base_country = 'GB';
    }
    if ( $country == 'UK' ) {
        $country = 'GB';
    }

    return ( $country && $country === $base_country ) ? true : false;
}

function wpinv_country_name( $country_code = '' ) { 
    $countries = wpinv_get_country_list();
    $country_code = $country_code == 'UK' ? 'GB' : $country_code;
    $country = isset( $countries[$country_code] ) ? $countries[$country_code] : $country_code;

    return apply_filters( 'wpinv_country_name', $country, $country_code );
}

function wpinv_get_default_state() {
	$state = wpinv_get_option( 'default_state', false );

	return apply_filters( 'wpinv_default_state', $state );
}

function wpinv_state_name( $state_code = '', $country_code = '' ) {
    $state = $state_code;
    
    if ( !empty( $country_code ) ) {
        $states = wpinv_get_country_states( $country_code );
        
        $state = !empty( $states ) && isset( $states[$state_code] ) ? $states[$state_code] : $state;
    }

    return apply_filters( 'wpinv_state_name', $state, $state_code, $country_code );
}

function wpinv_store_address() {
    $address = wpinv_get_option( 'store_address', '' );

    return apply_filters( 'wpinv_store_address', $address );
}

function wpinv_get_user_address( $user_id = 0, $with_default = true ) {
    global $wpi_userID;
    
    if( empty( $user_id ) ) {
        $user_id = !empty( $wpi_userID ) ? $wpi_userID : get_current_user_id();
    }
    
    $address_fields = array(
        ///'user_id',
        'first_name',
        'last_name',
        'company',
        'vat_number',
        ///'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'zip',
    );
    
    $user_info = get_userdata( $user_id );
    
    $address = array();
    $address['user_id'] = $user_id;
    $address['email'] = !empty( $user_info ) ? $user_info->user_email : '';
    foreach ( $address_fields as $field ) {
        $address[$field] = get_user_meta( $user_id, '_wpinv_' . $field, true );
    }

    if ( !empty( $user_info ) ) {
        if( empty( $address['first_name'] ) )
            $address['first_name'] = $user_info->first_name;
        
        if( empty( $address['last_name'] ) )
            $address['last_name'] = $user_info->last_name;
    }
    
    $address['name'] = trim( trim( $address['first_name'] . ' ' . $address['last_name'] ), "," );
    
    if( empty( $address['state'] ) && $with_default )
        $address['state'] = wpinv_get_default_state();

    if( empty( $address['country'] ) && $with_default )
        $address['country'] = wpinv_get_default_country();


    return $address;
}

/**
 * Get all continents.
 * 
 * @since 1.0.14
 * @param string $return What to return.
 * @return array
 */
function wpinv_get_continents( $return = 'all' ) {

    $continents = wpinv_get_data( 'continents' );

    switch( $return ) {
        case 'name' :
            return wp_list_pluck( $continents, 'name' );
            break;
        case 'countries' :
            return wp_list_pluck( $continents, 'countries' );
            break;
        default :
            return $continents;
            break;
    }

}

/**
 * Get continent code for a country code.
 * 
 * @since 1.0.14
 * @param string $country Country code. If no code is specified, defaults to the default country.
 * @return string
 */
function wpinv_get_continent_code_for_country( $country = false ) {

    $country = wpinv_sanitize_country( $country );
    
	foreach ( wpinv_get_continents( 'countries' ) as $continent_code => $countries ) {
		if ( false !== array_search( $country, $countries, true ) ) {
			return $continent_code;
		}
	}

    return '';
    
}

/**
 * Get all calling codes.
 * 
 * @since 1.0.14
 * @param string $country Country code. If no code is specified, defaults to the default country.
 * @return array
 */
function wpinv_get_country_calling_code( $country = null) {

    $country = wpinv_sanitize_country( $country );
    $codes   = wpinv_get_data( 'phone-codes' );
    $code    = isset( $codes[ $country ] ) ? $codes[ $country ] : '';

    if ( is_array( $code ) ) {
        return $code[0];
    }
    return $code;

}

/**
 * Get all countries.
 * 
 * @param bool $first_empty Whether or not the first item in the list should be empty
 * @return array
 */
function wpinv_get_country_list( $first_empty = false ) {
    return wpinv_maybe_add_empty_option( apply_filters( 'wpinv_countries', wpinv_get_data( 'countries' ) ), $first_empty );
}

/**
 * Retrieves a given country's states.
 * 
 * @param string $country Country code. If no code is specified, defaults to the default country.
 * @param bool $first_empty Whether or not the first item in the list should be empty
 * @return array
 */
function wpinv_get_country_states( $country = null, $first_empty = false ) {
    
    // Prepare the country.
    $country = wpinv_sanitize_country( $country );

    // Fetch all states.
    $all_states = wpinv_get_data( 'states' );

    // Fetch the specified country's states.
    $states     = isset( $all_states[ $country ] ) ? $all_states[ $country ] : array() ;
    $states     = apply_filters( "wpinv_{$country}_states", $states );
    $states     = apply_filters( 'wpinv_country_states', $states, $country );

    asort( $states );
     
    return wpinv_maybe_add_empty_option( $states, $first_empty );
}

/**
 * Returns US states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_us_states_list() {
    return apply_filters( 'wpinv_usa_states', wpinv_get_country_states( 'US' ) );
}

/**
 * Returns Canada states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_canada_states_list() {
    return apply_filters( 'wpinv_canada_provinces', wpinv_get_country_states( 'CA' ) );
}

/**
 * Returns australian states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_australia_states_list() {
    return apply_filters( 'wpinv_australia_states', wpinv_get_country_states( 'AU' ) );
}

/**
 * Returns bangladeshi states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_bangladesh_states_list() {
    return apply_filters( 'wpinv_bangladesh_states', wpinv_get_country_states( 'BD' ) );
}

/**
 * Returns brazilianUS states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_brazil_states_list() {
    return apply_filters( 'wpinv_brazil_states', wpinv_get_country_states( 'BR' ) );
}

/**
 * Returns bulgarian states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_bulgaria_states_list() {
    return apply_filters( 'wpinv_bulgaria_states', wpinv_get_country_states( 'BG' ) );
}

/**
 * Returns hong kon states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_hong_kong_states_list() {
    return apply_filters( 'wpinv_hong_kong_states', wpinv_get_country_states( 'HK' ) );
}

/**
 * Returns hungarian states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_hungary_states_list() {
    return apply_filters( 'wpinv_hungary_states', wpinv_get_country_states( 'HU' ) );
}

/**
 * Returns japanese states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_japan_states_list() {
    return apply_filters( 'wpinv_japan_states', wpinv_get_country_states( 'JP' ) );
}

/**
 * Returns chinese states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_china_states_list() {
    return apply_filters( 'wpinv_china_states', wpinv_get_country_states( 'CN' ) );
}

/**
 * Returns new zealand states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_new_zealand_states_list() {
    return apply_filters( 'wpinv_new_zealand_states', wpinv_get_country_states( 'NZ' ) );
}

/**
 * Returns perusian states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_peru_states_list() {
    return apply_filters( 'wpinv_peru_states', wpinv_get_country_states( 'PE' ) );
}

/**
 * Returns indonesian states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_indonesia_states_list() {
    return apply_filters( 'wpinv_indonesia_states', wpinv_get_country_states( 'ID' ) );
}

/**
 * Returns indian states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_india_states_list() {
    return apply_filters( 'wpinv_india_states', wpinv_get_country_states( 'IN' ) );
}

/**
 * Returns iranian states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_iran_states_list() {
    return apply_filters( 'wpinv_iran_states', wpinv_get_country_states( 'IR' ) );
}

/**
 * Returns italian states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_italy_states_list() {
    return apply_filters( 'wpinv_italy_states', wpinv_get_country_states( 'IT' ) );
}

/**
 * Returns malaysian states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_malaysia_states_list() {
    return apply_filters( 'wpinv_malaysia_states', wpinv_get_country_states( 'MY' ) );
}

/**
 * Returns mexican states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_mexico_states_list() {
    return apply_filters( 'wpinv_mexico_states', wpinv_get_country_states( 'MX' ) );
}

/**
 * Returns nepal states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_nepal_states_list() {
    return apply_filters( 'wpinv_nepal_states', wpinv_get_country_states( 'NP' ) );
}

/**
 * Returns south african states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_south_africa_states_list() {
    return apply_filters( 'wpinv_south_africa_states', wpinv_get_country_states( 'ZA' ) );
}

/**
 * Returns thailandese states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_thailand_states_list() {
    return apply_filters( 'wpinv_thailand_states', wpinv_get_country_states( 'TH' ) );
}

/**
 * Returns turkish states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_turkey_states_list() {
    return apply_filters( 'wpinv_turkey_states', wpinv_get_country_states( 'TR' ) );
}

/**
 * Returns spanish states.
 * 
 * @deprecated 1.0.14
 * @return array
 */
function wpinv_get_spain_states_list() {
    return apply_filters( 'wpinv_spain_states', wpinv_get_country_states( 'ES' ) );
}

function wpinv_get_states_field() {
	if( empty( $_POST['country'] ) ) {
		$_POST['country'] = wpinv_get_default_country();
	}
	$states = wpinv_get_country_states( sanitize_text_field( $_POST['country'] ) );

	if( !empty( $states ) ) {
		$sanitized_field_name = sanitize_text_field( $_POST['field_name'] );
        
        $args = array(
			'name'    => $sanitized_field_name,
			'id'      => $sanitized_field_name,
			'class'   => $sanitized_field_name . ' wpinv-select wpi_select2',
			'options' => array_merge( array( '' => '' ), $states ),
			'show_option_all'  => false,
			'show_option_none' => false
		);

		$response = wpinv_html_select( $args );

	} else {
		$response = 'nostates';
	}

	return $response;
}

function wpinv_default_billing_country( $country = '', $user_id = 0 ) {
    $country = !empty( $country ) ? $country : wpinv_get_default_country();
    
    return apply_filters( 'wpinv_default_billing_country', $country, $user_id );
}

/**
 * Retrieves the address format to use on Invoices.
 * 
 * @since 1.0.13
 * @see `wpinv_get_invoice_address_replacements`
 * @return string
 */
function wpinv_get_full_address_format() {

    $format = "{{address}} \n\n {{city}}, {{state}} \n\n {{country}} {{zip}}";
    
    /**
	 * Filters the address format to use on Invoices.
     * 
     * New lines will be replaced by a `br` element. Double new lines will be replaced by a paragraph. HTML tags are allowed.
	 *
	 * @since 1.0.13
	 *
	 * @param string $format  The address format to use.
	 */
    return apply_filters( 'wpinv_get_full_address_format', $format );
}

/**
 * Retrieves the address format replacements to use on Invoices.
 * 
 * @since 1.0.13
 * @see `wpinv_get_full_address_format`
 * @param array $billing_details customer's billing details
 * @return array
 */
function wpinv_get_invoice_address_replacements( $billing_details ) {

    $replacements = array(
        'address'        => '',
        'city'           => '',
        'state'          => '',
        'country'        => '',
        'country_code'   => '',
        'zip'            => '',
    );

    if( ! empty( $billing_details['address'] ) ) {
        $replacements['address'] = sanitize_text_field( $billing_details['address'] );
    }

    if( ! empty( $billing_details['city'] ) ) {
        $replacements['city'] = sanitize_text_field( $billing_details['city'] );
    }

    if( ! empty( $billing_details['zip'] ) ) {
        $replacements['zip'] = sanitize_text_field( $billing_details['zip'] );
    }
    
    $billing_country = !empty( $billing_details['country'] ) ? $billing_details['country'] : '';
    if ( !empty( $billing_details['state'] ) ) {
        $replacements['state'] = sanitize_text_field( wpinv_state_name( $billing_details['state'], $billing_country ) );
    }

    if ( !empty( $billing_country ) ) {
        $replacements['country']      = wpinv_country_name( $billing_country );
        $replacements['country_code'] = sanitize_text_field( $billing_country );
    }
    
    /**
	 * Filters the address format replacements to use on Invoices.
     * 
	 *
	 * @since 1.0.13
	 *
	 * @param array $replacements  The address replacements to use.
     * @param array $billing_details  The billing details to use.
	 */
    return apply_filters( 'wpinv_get_invoice_address_replacements', $replacements, $billing_details );
}