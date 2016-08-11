<?php
// MUST have WordPress.
if ( !defined( 'WPINC' ) ) {
    exit( 'Do NOT access this file directly: ' . basename( __FILE__ ) );
}

function wpinv_geodir_integration() {    
    if (!(defined( 'DOING_AJAX' ) && DOING_AJAX)) {
        // Add  fields for force upgrade
        if ( defined('INVOICE_TABLE') && !get_option('wpinv_gdp_column') ) {
            geodir_add_column_if_not_exist( INVOICE_TABLE, 'invoice_id', 'INT( 11 ) NOT NULL DEFAULT 0' );
            
            update_option('wpinv_gdp_column', '1');
        }
        // Merge price packages
        wpinv_merge_gd_packages_to_items();
    }
}
add_action( 'admin_init', 'wpinv_geodir_integration' );

function wpinv_update_package_item($package_id) {
    wpinv_error_log( $package_id, 'wpinv_update_package_item()', __FILE__, __LINE__ );
    return wpinv_merge_gd_package_to_item($package_id, true);
}
add_action('geodir_after_save_package', 'wpinv_update_package_item', 10, 1);

function wpinv_merge_gd_packages_to_items( $force = false ) {    
    if ( $merged = get_option( 'wpinv_merge_gd_packages' ) && !$force ) {
        return true;
    }
    
    $packages = geodir_package_list_info();
    
    foreach ( $packages as $key => $package ) {
        wpinv_merge_gd_package_to_item( $package->pid, $force, $package );
    }
    
    if ( !$merged ) {
        update_option( 'wpinv_merge_gd_packages', 1 );
    }
    
    return true;
}

function wpinv_get_gd_package_item($package_id, $create = false) {
    $item = wpinv_get_item_by('package_id', $package_id);
    
    if (!$create) {
        return $item;
    }
    
    return wpinv_merge_gd_package_to_item($package_id, true);
}

function wpinv_merge_gd_package_to_item($package_id, $force = false, $package = NULL) {
    if (empty($package_id)) {
        return false;
    }
    
    $item = wpinv_get_item_by('package_id', $package_id);

    if (!$force && !empty($item)) {
        return $item;
    }

    $package = empty($package) ? geodir_get_package_info($package_id) : $package;

    if (empty($package)) {
        return false;
    }
        
    $meta                       = array();
    $meta['type']               = 'package';
    $meta['package_id']         = $package_id;
    $meta['post_type']          = $package->post_type;
    $meta['cpt_singular_name']  = get_post_type_singular_label($package->post_type);
    $meta['cpt_name']           = get_post_type_plural_label($package->post_type);
    $meta['price']              = wpinv_format_amount( $package->amount, NULL, true );
    $meta['vat_rule']           = 'digital';
    $meta['vat_class']          = $meta['price'] > 0 ? '_standard' : '_exempt';
    
    if ( !empty( $package->sub_active ) ) {
        $meta['is_recurring']       = 1;
        $meta['recurring_period']   = $package->sub_units;
        $meta['recurring_interval'] = absint( $package->sub_units_num );
        $meta['recurring_limit']    = absint( $package->sub_units_num_times );
    } else {
        $meta['is_recurring']       = 0;
        $meta['recurring_period']   = '';
        $meta['recurring_interval'] = '';
        $meta['recurring_limit']    = '';
    }
    
    $data  = array( 
        'post_title'    => $package->title,
        'post_excerpt'  => $package->title_desc,
        'post_status'   => $package->status == 1 ? 'publish' : 'pending',
        'meta'          => $meta
    );

    if (!empty($item)) {
        $item->update($data);
    } else {
        $item = new WPInv_Item();
        $item->create($data);
    }
    
    return $item;
}

function wpinv_print_checkout_errors() {
    global $wpi_session;
    wpinv_print_errors();
}
add_action( 'geodir_checkout_page_content', 'wpinv_print_checkout_errors', -10 );

function wpinv_checkout_fields() {    
    if ( wpinv_use_taxes() && geodir_is_page( 'checkout' ) ) {
        $logged_in          = is_user_logged_in();
        $billing_details    = wpinv_checkout_billing_details();

        if ( !empty( $billing_details['country'] ) ) {
            $selected_country = $billing_details['country'];
        } else {
            $selected_country = apply_filters( 'wpinv-get-country', '' );
            
            if ( empty( $selected_country ) ) {
                $selected_country = wpinv_get_default_country();
            }
        }
        ?>
        <div id="wpinv-fields" class="clearfix">
            <div id="wpi-billing" class="wpi-billing clearfix">
                <span><legend><?php _e( 'Billing Details', 'invoicing' );?></legend></span>
                <div id="wpinv-fields-box">
                    <p class="wpi-cart-field wpi-col2 wpi-colf">
                        <label for="wpinv_first_name" class="wpi-label"><?php _e( 'First Name', 'invoicing' );?><span class="wpi-required">*</span></label>
                        <?php
                        echo wpinv_html_text( array(
                                'id'            => 'wpinv_first_name',
                                'name'          => 'wpinv_first_name',
                                'value'         => $billing_details['first_name'],
                                'class'         => 'wpi-input required',
                                'placeholder'   => __( 'First name', 'invoicing' ),
                                'required'      => true,
                            ) );
                        ?>
                    </p>
                    <p class="wpi-cart-field wpi-col2 wpi-coll">
                        <label for="wpinv_last_name" class="wpi-label"><?php _e( 'Last Name', 'invoicing' );?></label>
                        <?php
                        echo wpinv_html_text( array(
                                'id'            => 'wpinv_last_name',
                                'name'          => 'wpinv_last_name',
                                'value'         => $billing_details['last_name'],
                                'class'         => 'wpi-input',
                                'placeholder'   => __( 'Last name', 'invoicing' ),
                            ) );
                        ?>
                    </p>
                    <p class="wpi-cart-field wpi-col2 wpi-colf">
                        <label for="wpinv_email" class="wpi-label"><?php _e( 'Email Address', 'invoicing' );?><span class="wpi-required">*</span></label>
                        <?php
                        echo wpinv_html_text( array(
                                'id'            => 'wpinv_email',
                                'name'          => 'wpinv_email',
                                'value'         => $billing_details['email'],
                                'class'         => 'wpi-input required',
                                'placeholder'   => __( 'Email address', 'invoicing' ),
                                'required'      => true,
                            ) );
                        ?>
                    </p>
                    <p class="wpi-cart-field wpi-col2 wpi-coll">
                        <label for="wpinv_phone" class="wpi-label"><?php _e( 'Phone', 'invoicing' );?></label>
                        <?php
                        echo wpinv_html_text( array(
                                'id'            => 'wpinv_phone',
                                'name'          => 'wpinv_phone',
                                'value'         => $billing_details['phone'],
                                'class'         => 'wpi-input',
                                'placeholder'   => __( 'Phone', 'invoicing' ),
                            ) );
                        ?>
                    </p>
                    <p class="wpi-cart-field wpi-col2 wpi-colf">
                        <label for="wpinv_address" class="wpi-label"><?php _e( 'Address', 'invoicing' );?><span class="wpi-required">*</span></label>
                        <?php
                        echo wpinv_html_text( array(
                                'id'            => 'wpinv_address',
                                'name'          => 'wpinv_address',
                                'value'         => $billing_details['address'],
                                'class'         => 'wpi-input required',
                                'placeholder'   => __( 'Address', 'invoicing' ),
                                'required'      => true,
                            ) );
                        ?>
                    </p>
                    <p class="wpi-cart-field wpi-col2 wpi-coll">
                        <label for="wpinv_city" class="wpi-label"><?php _e( 'City', 'invoicing' );?><span class="wpi-required">*</span></label>
                        <?php
                        echo wpinv_html_text( array(
                                'id'            => 'wpinv_city',
                                'name'          => 'wpinv_city',
                                'value'         => $billing_details['city'],
                                'class'         => 'wpi-input required',
                                'placeholder'   => __( 'City', 'invoicing' ),
                                'required'      => true,
                            ) );
                        ?>
                    </p>
                    <p id="wpinv_country_box" class="wpi-cart-field wpi-col2 wpi-colf">
                        <label for="wpinv_country" class="wpi-label"><?php _e( 'Country', 'invoicing' );?><span class="wpi-required">*</span></label>
                        <?php echo wpinv_html_select( array(
                            'options'          => wpinv_get_country_list(),
                            'name'             => 'wpinv_country',
                            'id'               => 'wpinv_country',
                            'selected'         => $selected_country,
                            'show_option_all'  => false,
                            'show_option_none' => false,
                            'class'            => 'wpi-input required',
                            'placeholder'      => __( 'Choose a country', 'invoicing' ),
                            'required'          => true,
                        ) ); ?>
                    </p>
                    <p id="wpinv_state_box" class="wpi-cart-field wpi-col2 wpi-coll">
                        <label for="wpinv_state" class="wpi-label"><?php _e( 'State / Province', 'invoicing' );?><span class="wpi-required">*</span></label>
                        <?php
                        $states = wpinv_get_country_states( $selected_country );
                        if( !empty( $states ) ) {
                            echo wpinv_html_select( array(
                                'options'          => $states,
                                'name'             => 'wpinv_state',
                                'id'               => 'wpinv_state',
                                'selected'         => $billing_details['state'],
                                'show_option_all'  => false,
                                'show_option_none' => false,
                                'class'            => 'wpi-input required',
                                'placeholder'      => __( 'Choose a state', 'invoicing' ),
                                'required'         => true,
                            ) );
                        } else {
                            echo wpinv_html_text( array(
                                'name'          => 'wpinv_state',
                                'value'         => $billing_details['state'],
                                'id'            => 'wpinv_state',
                                'class'         => 'wpi-input required',
                                'placeholder'   => __( 'State / Province', 'invoicing' ),
                                'required'      => true,
                            ) );
                        }
                        ?>
                    </p>
                    <p class="wpi-cart-field wpi-col2 wpi-colf">
                        <label for="wpinv_zip" class="wpi-label"><?php _e( 'ZIP / Postcode', 'invoicing' );?></label>
                        <?php
                        echo wpinv_html_text( array(
                                'name'          => 'wpinv_zip',
                                'value'         => $billing_details['zip'],
                                'id'            => 'wpinv_zip',
                                'class'         => 'wpi-input',
                                'placeholder'   => __( 'ZIP / Postcode', 'invoicing' ),
                            ) );
                        ?>
                    </p>
                </div>
            </div>
            <?php do_action( 'wpinv_after_billing_fields', $billing_details ); ?>
        </div>
        <?php
    }
}
//add_action( 'geodir_checkout_after_form_fields', 'wpinv_checkout_fields' );

function wpinv_cpt_save( $invoice_id, $update = false, $pre_status = NULL ) {
    global $wpi_nosave;
    wpinv_error_log( 'IN', 'wpinv_cpt_save( ' . $invoice_id . ', ' . $update . ', ' . $pre_status . ' )', __FILE__, __LINE__ );
    $invoice_info = geodir_get_invoice( $invoice_id );
    
    $wpi_invoice_id  = !empty( $invoice_info->invoice_id ) ? $invoice_info->invoice_id : 0;
    
    if (!empty($invoice_info)) {
        $wpi_invoice = $wpi_invoice_id > 0 ? wpinv_get_invoice( $wpi_invoice_id ) : NULL;
        
        if ( !empty( $wpi_invoice ) ) { // update invoice
            $save = false;
            if ($invoice_info->coupon_code !== $wpi_invoice->discount_code || (float)$invoice_info->discount < (float)$wpi_invoice->discount || (float)$invoice_info->discount > (float)$wpi_invoice->discount) {
                $save = true;
                $wpi_invoice->set('discount_code', $invoice_info->coupon_code);
                $wpi_invoice->set('discount', $invoice_info->discount);
            }
            
            if ($invoice_info->paymentmethod !== $wpi_invoice->gateway) {
                $save = true;
                $gateway = !empty( $invoice_info->paymentmethod ) ? $invoice_info->paymentmethod : 'manually';
                $gateway_title = !empty( $invoice_info->paymentmethod ) ? geodir_payment_method_title( $invoice_info->paymentmethod ) : __( 'Manual Payment', 'invoicing' );
                $save = true;
                $wpi_invoice->set('gateway', $gateway);
                $wpi_invoice->set('gateway_title', $gateway_title);
            }
            
            if ( ( $status = wpinv_to_wpi_status( $invoice_info->status ) ) !== $wpi_invoice->status ) {
                $save = true;
                $wpi_invoice->set( 'status', $status );
            }
            
            wpinv_error_log( 'UPDATE', 'wpinv_cpt_save()', __FILE__, __LINE__ );
            if ($save) {
                $wpi_nosave = true;
                $wpi_invoice->recalculate_total();
                $wpi_invoice->save();
                wpinv_error_log( 'save()', 'wpinv_cpt_save()', __FILE__, __LINE__ );
            }
            
            return $wpi_invoice;
        } else { // create invoice
            wpinv_error_log( 'CREATE', 'wpinv_cpt_save()', __FILE__, __LINE__ );
            $user_info = get_userdata( $invoice_info->user_id );
            
            if ( !empty( $pre_status ) ) {
                $invoice_info->status = $pre_status;
            }
            $status = wpinv_to_wpi_status( $invoice_info->status );
            
            $invoice_data                   = array();
            $invoice_data['invoice_id']     = $wpi_invoice_id;
            $invoice_data['status']         = $status;
            if ( $update ) {
                //$invoice_data['private_note']   = __( 'Invoice was updated.', 'invoicing' );
            } else {
                $invoice_data['private_note']   = wp_sprintf( __( 'Invoice was created with status %s.', 'invoicing' ), wpinv_status_nicename( $status ) );
            }
            $invoice_data['user_id']        = $invoice_info->user_id;
            $invoice_data['created_via']    = 'API';
            
            if ( !empty( $invoice_info->date ) ) {
                $invoice_data['created_date']   = $invoice_info->date;
            }
            
            $paymentmethod = !empty( $invoice_info->paymentmethod ) ? $invoice_info->paymentmethod : 'manually';
            $payment_method_title = !empty( $invoice_info->paymentmethod ) ? geodir_payment_method_title( $invoice_info->paymentmethod ) : __( 'Manual Payment', 'invoicing' );
            
            $invoice_data['payment_details'] = array( 
                'gateway'           => $paymentmethod, 
                'gateway_title'     => $payment_method_title,
                'currency'          => geodir_get_currency_type(),
                'paid'              => $status === 'publish' ? true : false
            );
            
            $user_address = wpinv_get_user_address( $invoice_info->user_id, false );
            
            $invoice_data['user_info'] = array( 
                'user_id'       => $invoice_info->user_id, 
                'first_name'    => $user_address['first_name'],
                'last_name'     => $user_address['last_name'],
                'email'         => $user_address['email'],
                'company'       => $user_address['company'],
                'vat_number'    => $user_address['vat_number'],
                'phone'         => $user_address['phone'],
                'address'       => $user_address['address'],
                'city'          => $user_address['city'],
                'country'       => $user_address['country'],
                'state'         => $user_address['state'],
                'zip'           => $user_address['zip'],
            );
            
            if ($invoice_info->discount > 0) {
                $invoice_data['discount']       = $invoice_info->discount;
                $invoice_data['discount_code']  = $invoice_info->coupon_code;
            }
            
            $post_item = wpinv_get_gd_package_item($invoice_info->package_id);
            
            if ( !empty( $post_item ) ) {
                $cart_details  = array();
                $cart_details[] = array(
                    'id'                => $post_item->ID,
                    'name'              => $post_item->get_name(),
                    'item_price'        => $post_item->get_price(),
                    'meta'              => array( 
                                            'post_id'       => $invoice_info->post_id,
                                            'invoice_title' => $invoice_info->post_title
                                        ),
                );
                
                $invoice_data['cart_details']  = $cart_details;
            }

            $data = array( 'invoice' => $invoice_data );

            $wpinv_api = new WPInv_API();
            $data = $wpinv_api->insert_invoice( $data );
            
            if ( is_wp_error( $data ) ) {
                wpinv_error_log( 'WPInv_Invoice: ' . $data->get_error_message() );
            } else {
                if ( !empty( $data ) ) {
                    update_post_meta( $data->ID, '_wpinv_gdp_id', $invoice_id );
                    
                    $update_data = array();
                    $update_data['tax_amount'] = $data->get_tax();
                    $update_data['paied_amount'] = $data->get_total();
                    $update_data['invoice_id'] = $data->ID;
                    
                    global $wpdb;
                    $wpdb->update( INVOICE_TABLE, $update_data, array( 'id' => $invoice_id ) );
                    
                    return $data;
                } else {
                    if ( $update ) {
                        wpinv_error_log( 'WPInv_Invoice: ' . __( 'Fail to update invoice.', 'invoicing' ) );
                    } else {
                        wpinv_error_log( 'WPInv_Invoice: ' . __( 'Fail to create invoice.', 'invoicing' ) );
                    }
                }
            }
        }
    }
    
    return false;
}
add_action('geodir_payment_invoice_created', 'wpinv_cpt_save', 11, 3);

function wpinv_cpt_update( $invoice_id ) {
    wpinv_error_log( '===>', 'wpinv_cpt_save()', __FILE__, __LINE__ );
    return wpinv_cpt_save( $invoice_id, true );
}
add_action('geodir_payment_invoice_updated', 'wpinv_cpt_update', 11, 1);

function wpinv_payment_status_changed( $invoice_id, $new_status, $old_status = 'pending', $subscription = false ) {
    wpinv_error_log( 'IN', 'wpinv_payment_status_changed()', __FILE__, __LINE__ );
    $invoice_info = geodir_get_invoice( $invoice_id );
    if ( empty( $invoice_info ) ) {
        return false;
    }

    $invoice = !empty( $invoice_info->invoice_id ) ? wpinv_get_invoice( $invoice_info->invoice_id ) : NULL;
    if ( !empty( $invoice ) ) {
        $new_status = wpinv_to_wpi_status($new_status);
        $invoice    = wpinv_update_payment_status( $invoice->ID, $new_status );
    } else {
        $invoice = wpinv_cpt_save( $invoice_id );
    }
    
    return $invoice;
}
add_action( 'geodir_payment_invoice_status_changed', 'wpinv_payment_status_changed', 11, 4 );

function wpinv_transaction_details_note( $invoice_id, $html ) {
    $invoice_info = geodir_get_invoice( $invoice_id );
    if ( empty( $invoice_info ) ) {
        return false;
    }

    $wpi_invoice_id = !empty( $invoice_info->invoice_id ) ? $invoice_info->invoice_id : NULL;
    
    if ( !$wpi_invoice_id ) {
        wpinv_error_log( '===>', 'wpinv_cpt_save()', __FILE__, __LINE__ );
        $invoice = wpinv_cpt_save( $invoice_id, false, $old_status );
        
        if ( !empty( $invoice ) ) {
            $wpi_invoice_id = $invoice->ID;
        }
    }

    $invoice = wpinv_get_invoice( $wpi_invoice_id );
    
    if ( empty( $invoice ) ) {
        return false;
    }
    
    return $invoice->add_note( $html, true );
}
add_action( 'geodir_payment_invoice_transaction_details_changed', 'wpinv_transaction_details_note', 11, 2 );

function wpinv_to_gdp_status( $status ) {
    $inv_status = $status ? $status : 'pending';
    
    switch ( $status ) {
        case 'publish':
            $inv_status = 'confirmed';
        break;
    }
    
    return $inv_status;
}

function wpinv_wpi_to_gdp_id( $invoice_id ) {
    global $wpdb;
    
    return $wpdb->get_var( $wpdb->prepare( "SELECT `id` FROM `" . INVOICE_TABLE . "` WHERE `invoice_id` = %d AND `invoice_id` > 0 ORDER BY id DESC LIMIT 1", array( (int)$invoice_id ) ) );
}

function wpinv_gdp_to_wpi_id( $invoice_id ) {
    $invoice = geodir_get_invoice( $invoice_id );    
    return ( empty( $invoice->invoice_id ) ? $invoice->invoice_id : false);
}

function wpinv_to_gdp_recalculate_total( $invoice, $wpi_nosave ) {
    global $wpdb;
    
    if ( !empty( $wpi_nosave ) ) {
        return;
    }
    
    $gdp_invoice_id = wpinv_wpi_to_gdp_id( $invoice->ID );
    //wpinv_error_log( $gdp_invoice_id, 'gdp_invoice_id', __FILE__, __LINE__ );
    
    if ( $gdp_invoice_id > 0 ) {
        $update_data = array();
        $update_data['tax_amount']      = $invoice->tax;
        $update_data['paied_amount']    = $invoice->total;
        $update_data['discount']        = $invoice->discount;
        $update_data['coupon_code']     = $invoice->discount_code;
        
        //wpinv_error_log( $update_data, 'update_data', __FILE__, __LINE__ );
        
        $wpdb->update( INVOICE_TABLE, $update_data, array( 'id' => $gdp_invoice_id ) );
    }
    
    return;
}
//add_action( 'wpinv_invoice_recalculate_total', 'wpinv_to_gdp_recalculate_total', 10, 2 );

function wpinv_gdp_to_wpi_invoice( $invoice_id ) {
    $invoice = geodir_get_invoice( $invoice_id );
    if ( empty( $invoice->invoice_id ) ) {
        return false;
    }
    
    return wpinv_get_invoice( $invoice->invoice_id );
}

/*
function wpinv_filter_cart_totals( $cart ) {
    if ( !( !empty( $cart->invoice_id ) && geodir_is_page( 'checkout' ) ) ) {
        return $cart;
    }
    
    $wpi_invoice = wpinv_get_invoice_cart( $cart->invoice_id );
    if ( empty( $wpi_invoice ) ) {
        return $cart;
    }
    
    $cart->tax_amount           = $wpi_invoice->get_tax();
    $cart->discount             = $wpi_invoice->get_discount();
    $cart->paied_amount         = $wpi_invoice->get_total();
    $cart->tax_amount_display   = $wpi_invoice->get_tax( true );
    $cart->discount_display     = $wpi_invoice->get_discount( true );
    $cart->paied_amount_display = $wpi_invoice->get_total( true );

    return $cart;
}
add_filter( 'geodir_payment_get_cart', 'wpinv_filter_cart_totals' );
*/

function wpinv_payment_set_coupon_code( $status, $invoice_id, $coupon_code ) {
    //wpinv_error_log( $status, 'wpinv_payment_set_coupon_code()', __FILE__, __LINE__ );
    $invoice = wpinv_gdp_to_wpi_invoice( $invoice_id );
    if ( empty( $invoice ) ) {
        return $status;
    }
    //wpinv_error_log( $invoice, 'invoice', __FILE__, __LINE__ );
    if ( $status === 1 || $status === 0 ) {
        if ( $status === 1 ) {
            $discount = geodir_get_discount_amount( $coupon_code, $invoice->get_subtotal() );
        } else {
            $discount = '';
            $coupon_code = '';
        }
        
        $invoice->set( 'discount', $discount );
        $invoice->set( 'discount_code', $coupon_code );
        $invoice->save();
        $invoice->recalculate_total();
        
        //wpinv_error_log( $invoice, 'invoice', __FILE__, __LINE__ );
    }
    
    return $status;
}
add_filter( 'geodir_payment_set_coupon_code', 'wpinv_payment_set_coupon_code', 10, 3 );

function wpinv_insert_invoice( $invoice_data = array() ) {
    wpinv_error_log( 'wpinv_insert_invoice()', '', __FILE__, __LINE__ );
    if ( empty( $invoice_data ) ) {
        return false;
    }

    // default invoice args, note that status is checked for validity in wpinv_create_invoice()
    $default_args = array(
        'status'        => !empty( $invoice_data['status'] ) ? $invoice_data['status'] : 'pending',
        'user_note'     => !empty( $invoice_data['note'] ) ? $invoice_data['note'] : null,
        'invoice_id'    => !empty( $invoice_data['invoice_id'] ) ? (int)$invoice_data['invoice_id'] : 0,
        'user_id'       => !empty( $invoice_data['user_id'] ) ? (int)$invoice_data['user_id'] : get_current_user_id(),
    );

    $invoice = wpinv_create_invoice( $default_args, $invoice_data );
    if ( is_wp_error( $invoice ) ) {
        return $invoice;
    }

    $gateway = !empty( $invoice_data['gateway'] ) ? $invoice_data['gateway'] : '';
    $gateway = empty( $gateway ) && isset( $_POST['gateway'] ) ? $_POST['gateway'] : $gateway;
    
    $user_info = array(
        'user_id'        => '',
        'first_name'     => '',
        'last_name'      => '',
        'email'          => '',
        'company'        => '',
        'phone'          => '',
        'address'        => '',
        'city'           => '',
        'country'        => wpinv_get_default_country(),
        'state'          => wpinv_get_default_state(),
        'zip'            => '',
        'vat_number'     => '',
        'vat_rate'       => '',
        'self_certified' => '',
        'discount'       => array(),
    );
    
    $user_info    = wp_parse_args( $invoice_data['user_info'], $user_info );
    
    $payment_details = array();
    if ( !empty( $invoice_data['payment_details'] ) ) {
        $payment_details = array(
            'gateway'           => 'manual',
            'gateway_title'     => __( 'Manual Payment', 'invoicing' ),
            'currency'          => geodir_get_currency_type(),
            'paid'              => false,
            'transaction_id'    => '',
        );
        $payment_details = wp_parse_args( $invoice_data['payment_details'], $payment_details );
    }

    $invoice->status         = !empty( $invoice_data['status'] ) ? $invoice_data['status'] : 'pending';
    if ( !empty( $payment_details ) ) {
        $invoice->currency       = $payment_details['currency'];
        $invoice->gateway        = $payment_details['gateway'];
        $invoice->gateway_title  = $payment_details['gateway_title'];
        $invoice->transaction_id = $payment_details['transaction_id'];
    }
    $invoice->user_info      = $user_info;
    $invoice->gateway        = $gateway;
    $invoice->user_id        = $user_info['user_id'];
    $invoice->email          = $user_info['email'];
    $invoice->first_name     = $user_info['first_name'];
    $invoice->last_name      = $user_info['last_name'];
    $invoice->address        = $user_info['address'];
    $invoice->company        = $user_info['company'];
    $invoice->vat_number     = $user_info['vat_number'];
    $invoice->phone          = $user_info['phone'];
    $invoice->city           = $user_info['city'];
    $invoice->country        = $user_info['country'];
    $invoice->state          = $user_info['state'];
    $invoice->zip            = $user_info['zip'];
    $invoice->discounts      = !empty( $user_info['discount'] ) ? $user_info['discount'] : array();
    $invoice->ip             = wpinv_get_ip();
    if ( !empty( $invoice_data['invoice_key'] ) ) {
        $invoice->key        = $invoice_data['invoice_key'];
    }
    $invoice->mode           = wpinv_is_test_mode() ? 'test' : 'live';
    $invoice->parent_invoice = !empty( $invoice_data['parent'] ) ? absint( $invoice_data['parent'] ) : '';
    
    // Add note
    if ( !empty( $invoice_data['user_note'] ) ) {
        $invoice->add_note( $invoice_data['user_note'], true );
    }
    
    if ( !empty( $invoice_data['private_note'] ) ) {
        $invoice->add_note( $invoice_data['private_note'] );
    }
    
    if ( is_array( $invoice_data['cart_details'] ) && !empty( $invoice_data['cart_details'] ) ) {
        foreach ( $invoice_data['cart_details'] as $key => $item ) {
            $item_id    = !empty( $item['id'] ) ? $item['id'] : 0;
            $quantity   = !empty( $item['quantity'] ) ? $item['quantity'] : 1;
            $name       = !empty( $item['name'] ) ? $item['name'] : '';
            $item_price = isset( $item['item_price'] ) ? $item['item_price'] : '';
            
            $post_item  = new WPInv_Item( $item_id );
            if ( !empty( $post_item ) ) {
                $name       = !empty( $name ) ? $name : $post_item->get_name();
                $item_price = $item_price !== '' ? $item_price : $post_item->get_price();
            } else {
                continue;
            }
            
            $args = array(
                'name'          => $name,
                'quantity'      => $quantity,
                'item_price'    => $item_price,
                'tax'           => !empty( $item['tax'] ) ? $item['tax'] : 0.00,
                'discount'      => isset( $item['discount'] ) ? $item['discount'] : 0,
                'meta'          => isset( $item['meta'] ) ? $item['meta'] : array(),
                'fees'          => isset( $item['fees'] ) ? $item['fees'] : array(),
            );
            //wpinv_error_log( $args, 'args', __FILE__, __LINE__ );

            $invoice->add_item( $item_id, $args );
        }
    }

    $invoice->increase_tax( wpinv_get_cart_fee_tax() );

    if ( isset( $invoice_data['post_date'] ) ) {
        $invoice->date = $invoice_data['post_date'];
    }

    //if ( wpinv_get_option( 'enable_sequential' ) ) {
        $number          = wp_sprintf( __( 'WPINV-%d', 'invoicing' ), $invoice->ID ); // TODO
        $invoice->number = $number;
        update_option( 'wpinv_last_invoice_number', $number );
    //}

    $invoice->save();
    wpinv_error_log( 'save()', 'invoice', __FILE__, __LINE__ );
    
    do_action( 'wpinv_insert_invoice', $invoice->ID, $invoice_data );

    if ( ! empty( $invoice->ID ) ) {
        // payment method (and payment_complete() if `paid` == true)
        if ( !empty( $payment_details['paid'] ) ) {
            //$invoice->payment_complete( !empty( $payment_details['transaction_id'] ) ? $payment_details['transaction_id'] : $invoice->ID );
        }
            
        return $invoice;
    }

    // Return false if no invoice was inserted
    return false;
}