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

final class WPInv_Invoice {
    public $ID  = 0;
    
    public $pending;
    public $items = array();
    public $user_info = array();
    public $payment_meta = array();
    
    public $new = false;
    public $number = '';
    public $mode = 'live';
    public $key = '';
    public $total = 0.00;
    public $subtotal = 0;
    public $tax = 0;
    public $fees = array();
    public $fees_total = 0;
    public $discounts = '';
        public $discount = 0;
        public $discount_code = 0;
    public $date = '';
    public $completed_date = '';
    public $status      = 'pending';
    public $post_status = 'pending';
    public $old_status = '';
    public $status_nicename = '';
    public $user_id = 0;
    public $first_name = '';
    public $last_name = '';
    public $email = '';
    public $phone = '';
    public $address = '';
    public $city = '';
    public $country = '';
    public $state = '';
    public $zip = '';
    public $transaction_id = '';
    public $ip = '';
    public $gateway = '';
    public $gateway_title = '';
    public $currency = '';
    public $cart_details = array();
    
    public $company = '';
    public $vat_number = '';
    public $vat_rate = '';
    public $self_certified = '';
    
    public $full_name = '';
    public $parent_invoice = 0;
    
    public function __construct( $invoice_id = false ) {
        if( empty( $invoice_id ) ) {
            return false;
        }

        $this->setup_invoice( $invoice_id );
    }

    public function get( $key ) {
        if ( method_exists( $this, 'get_' . $key ) ) {
            $value = call_user_func( array( $this, 'get_' . $key ) );
        } else {
            $value = $this->$key;
        }

        return $value;
    }

    public function set( $key, $value ) {
        $ignore = array( 'items', 'cart_details', 'fees', '_ID' );

        if ( $key === 'status' ) {
            $this->old_status = $this->status;
        }

        if ( ! in_array( $key, $ignore ) ) {
            $this->pending[ $key ] = $value;
        }

        if( '_ID' !== $key ) {
            $this->$key = $value;
        }
    }

    public function _isset( $name ) {
        if ( property_exists( $this, $name) ) {
            return false === empty( $this->$name );
        } else {
            return null;
        }
    }

    private function setup_invoice( $invoice_id ) {
        $this->pending = array();

        if ( empty( $invoice_id ) ) {
            return false;
        }

        $invoice = get_post( $invoice_id );

        if( !$invoice || is_wp_error( $invoice ) ) {
            return false;
        }

        if( 'wpi_invoice' !== $invoice->post_type ) {
            return false;
        }

        do_action( 'wpinv_pre_setup_invoice', $this, $invoice_id );
        
        // Primary Identifier
        $this->ID              = absint( $invoice_id );
        
        // We have a payment, get the generic payment_meta item to reduce calls to it
        $this->payment_meta    = $this->get_meta();
        $this->date            = $invoice->post_date;
        $this->completed_date  = $this->setup_completed_date();
        $this->status          = $invoice->post_status;
        $this->post_status     = $this->status;
        $this->mode            = $this->setup_mode();
        $this->parent_invoice  = $invoice->post_parent;
        $this->post_name       = $this->setup_post_name( $invoice );
        $this->status_nicename = $this->setup_status_nicename();

        // Items
        $this->fees            = $this->setup_fees();
        $this->cart_details    = $this->setup_cart_details();
        $this->items           = $this->setup_items();

        // Currency Based
        $this->total           = $this->setup_total();
        $this->tax             = $this->setup_tax();
        $this->fees_total      = $this->get_fees_total();
        $this->subtotal        = $this->setup_subtotal();
        $this->currency        = $this->setup_currency();
        
        // Gateway based
        $this->gateway         = $this->setup_gateway();
        $this->gateway_title   = $this->setup_gateway_title();
        $this->transaction_id  = $this->setup_transaction_id();
        
        // User based
        $this->ip              = $this->setup_ip();
        $this->user_id         = !empty( $invoice->post_author ) ? $invoice->post_author : get_current_user_id();///$this->setup_user_id();
        $this->email           = get_the_author_meta( 'email', $this->user_id );
        
        $this->user_info       = $this->setup_user_info();
                
        $this->first_name      = $this->user_info['first_name'];
        $this->last_name       = $this->user_info['last_name'];
        $this->company         = $this->user_info['company'];
        $this->vat_number      = $this->user_info['vat_number'];
        $this->vat_rate        = $this->user_info['vat_rate'];
        $this->self_certified  = $this->user_info['self_certified'];
        $this->address         = $this->user_info['address'];
        $this->city            = $this->user_info['city'];
        $this->country         = $this->user_info['country'];
        $this->state           = $this->user_info['state'];
        $this->zip             = $this->user_info['zip'];
        $this->phone           = $this->user_info['phone'];
        
        $this->discounts       = $this->user_info['discount'];
            $this->discount        = $this->setup_discount();
            $this->discount_code   = $this->setup_discount_code();

        // Other Identifiers
        $this->key             = $this->setup_invoice_key();
        $this->number          = $this->setup_invoice_number();
        
        $this->full_name       = trim( $this->first_name . ' '. $this->last_name );
        
        // Allow extensions to add items to this object via hook
        do_action( 'wpinv_setup_invoice', $this, $invoice_id );

        return true;
    }
    
    private function setup_status_nicename() {
        $all_invoice_statuses  = wpinv_get_invoice_statuses();
        
        $status = array_key_exists( $this->status, $all_invoice_statuses ) ? $all_invoice_statuses[$this->status] : ucfirst( $this->status );

        return $status;
    }
    
    private function setup_post_name( $post = NULL ) {
        $post_name = '';
        
        if ( !empty( $post ) ) {
            if( !empty( $post->post_name ) ) {
                $post_name = $post->post_name;
            } else if ( !empty( $post->ID ) && !empty( $post->post_title ) ) {
                $post_name = sanitize_title( $post->post_title );
                
                global $wpdb;
                $wpdb->update( $wpdb->posts, array( 'post_name' => $post_name ), array( 'ID' => $post->ID ) );
            }
        }

        $this->post_name   = $post_name;
    }
    
    private function setup_completed_date() {
        $invoice = get_post( $this->ID );

        if ( 'pending' == $invoice->post_status || 'preapproved' == $invoice->post_status ) {
            return false; // This invoice was never completed
        }

        $date = ( $date = $this->get_meta( '_wpinv_completed_date', true ) ) ? $date : $invoice->modified_date;

        return $date;
    }
    
    private function setup_cart_details() {
        $cart_details = isset( $this->payment_meta['cart_details'] ) ? maybe_unserialize( $this->payment_meta['cart_details'] ) : array();
        return $cart_details;
    }
    
    public function array_convert() {
        return get_object_vars( $this );
    }
    
    private function setup_items() {
        $items = isset( $this->payment_meta['items'] ) ? maybe_unserialize( $this->payment_meta['items'] ) : array();
        return $items;
    }
    
    private function setup_fees() {
        $payment_fees = isset( $this->payment_meta['fees'] ) ? $this->payment_meta['fees'] : array();
        return $payment_fees;
        /*
        $invoice_fees = $this->get_meta( '_wpinv_fees' );
        return $invoice_fees;
        */
    }
        
    private function setup_currency() {
        $currency = isset( $this->payment_meta['currency'] ) ? $this->payment_meta['currency'] : apply_filters( 'wpinv_currency_default', wpinv_get_currency(), $this );
        return $currency;
        /*
        $currency = $this->get_meta( '_wpinv_currency', true );
        $currency = $currency ? $currency : apply_filters( 'wpinv_currency_default', wpinv_get_currency(), $this );
        return $currency;
        */
    }
    
    private function setup_discount() {
        //$discount = $this->get_meta( '_wpinv_discount', true );
        $discount = $this->subtotal - ( $this->total - $this->tax - $this->fees_total );
        if ( $discount < 0 ) {
            $discount = 0;
        }
        $discount = wpinv_format_amount( $discount, NULL, true );
        
        return $discount;
    }
    
    private function setup_discount_code() {
        $discount_code = !empty( $this->discounts ) ? $this->discounts : $this->get_meta( '_wpinv_discount_code', true );
        return $discount_code;
    }
    
    private function setup_tax() {
        $tax = $this->get_meta( '_wpinv_tax', true );

        // We don't have tax as it's own meta and no meta was passed
        if ( '' === $tax ) {            
            $tax = isset( $this->payment_meta['tax'] ) ? $this->payment_meta['tax'] : 0;
        }

        return $tax;
        /*
        $tax = $this->get_meta( '_wpinv_tax', true );
        return $tax;
        */
    }

    private function setup_subtotal() {
        $subtotal     = 0;
        $cart_details = $this->cart_details;

        if ( is_array( $cart_details ) ) {
            foreach ( $cart_details as $item ) {
                if ( isset( $item['subtotal'] ) ) {
                    $subtotal += $item['subtotal'];
                }
            }
        } else {
            $subtotal  = $this->total;
            $tax       = wpinv_use_taxes() ? $this->tax : 0;
            $subtotal -= $tax;
        }

        return $subtotal;
    }
    
    private function setup_discounts() {
        $discounts = ! empty( $this->payment_meta['user_info']['discount'] ) ? $this->payment_meta['user_info']['discount'] : array();
        return $discounts;
    }
    
    private function setup_total() {
        $amount = $this->get_meta( '_wpinv_total', true );

        if ( empty( $amount ) && '0.00' != $amount ) {
            $meta   = $this->get_meta( '_wpinv_payment_meta', true );
            $meta   = maybe_unserialize( $meta );

            if ( isset( $meta['amount'] ) ) {
                $amount = $meta['amount'];
            }
        }

        return $amount;
    }
    
    private function setup_mode() {
        return $this->get_meta( '_wpinv_mode' );
    }

    private function setup_gateway() {
        $gateway = $this->get_meta( '_wpinv_gateway' );
        
        if ( empty( $gateway ) && 'publish' === $this->status || 'complete' === $this->status ) {
            $gateway = 'manual';
        }
        
        return $gateway;
    }
    
    private function setup_gateway_title() {
        $gateway_title = wpinv_get_gateway_checkout_label( $this->gateway );
        return $gateway_title;
    }

    private function setup_transaction_id() {
        $transaction_id = $this->get_meta( '_wpinv_transaction_id' );

        if ( empty( $transaction_id ) || (int) $transaction_id === (int) $this->ID ) {
            $gateway        = $this->gateway;
            $transaction_id = apply_filters( 'wpinv_get_invoice_transaction_id-' . $gateway, $this->ID );
        }

        return $transaction_id;
    }

    private function setup_ip() {
        $ip = $this->get_meta( '_wpinv_user_ip' );
        return $ip;
    }

    ///private function setup_user_id() {
        ///$user_id = $this->get_meta( '_wpinv_user_id' );
        ///return $user_id;
    ///}
        
    private function setup_first_name() {
        $first_name = $this->get_meta( '_wpinv_first_name' );
        return $first_name;
    }
    
    private function setup_last_name() {
        $last_name = $this->get_meta( '_wpinv_last_name' );
        return $last_name;
    }
    
    private function setup_company() {
        $company = $this->get_meta( '_wpinv_company' );
        return $company;
    }
    
    private function setup_vat_number() {
        $vat_number = $this->get_meta( '_wpinv_vat_number' );
        return $vat_number;
    }
    
    private function setup_vat_rate() {
        $vat_rate = $this->get_meta( '_wpinv_vat_rate' );
        return $vat_rate;
    }
    
    private function setup_self_certified() {
        $self_certified = $this->get_meta( '_wpinv_self_certified' );
        return $self_certified;
    }
    
    ///private function setup_email() {
        ///$email = $this->get_meta( '_wpinv_email' );
        ///return $email;
    ///}
    
    private function setup_phone() {
        $phone = $this->get_meta( '_wpinv_phone' );
        return $phone;
    }
    
    private function setup_address() {
        //$address = ! empty( $this->payment_meta['user_info']['address'] ) ? $this->payment_meta['user_info']['address'] : '';
        //return $address;
        $address = $this->get_meta( '_wpinv_address', true );
        return $address;
    }
    
    private function setup_city() {
        $city = $this->get_meta( '_wpinv_city', true );
        return $city;
    }
    
    private function setup_country() {
        $country = $this->get_meta( '_wpinv_country', true );
        return $country;
    }
    
    private function setup_state() {
        $state = $this->get_meta( '_wpinv_state', true );
        return $state;
    }
    
    private function setup_zip() {
        $zip = $this->get_meta( '_wpinv_zip', true );
        return $zip;
    }

    private function setup_user_info() {
        $defaults = array(
            'user_id'        => $this->user_id,
            'first_name'     => $this->first_name,
            'last_name'      => $this->last_name,
            'email'          => get_the_author_meta( 'email', $this->user_id ),
            'phone'          => $this->phone,
            'address'        => $this->address,
            'city'           => $this->city,
            'country'        => $this->country,
            'state'          => $this->state,
            'zip'            => $this->zip,
            'company'        => $this->company,
            'vat_number'     => $this->vat_number,
            'vat_rate'       => $this->vat_rate,
            'self_certified' => $this->self_certified,
            'discount'       => $this->discounts,
        );
        
        $user_info = array();
        if ( isset( $this->payment_meta['user_info'] ) ) {
            $user_info = maybe_unserialize( $this->payment_meta['user_info'] );
            
            if ( !empty( $user_info ) && isset( $user_info['user_id'] ) && $post = get_post( $this->ID ) ) {
                $this->user_id = $post->post_author;
                $this->email = get_the_author_meta( 'email', $this->user_id );
                
                $user_info['user_id'] = $this->user_id;
                $user_info['email'] = $this->email;
                $this->payment_meta['user_id'] = $this->user_id;
                $this->payment_meta['email'] = $this->email;
            }
        }
        
        $user_info    = wp_parse_args( $user_info, $defaults );
        
        // Get the user, but only if it's been created
        $user = get_userdata( $this->user_id );
        
        if ( !empty( $user ) && $user->ID > 0 ) {
            if ( empty( $user_info ) ) {
                $user_info = array(
                    'user_id'    => $user->ID,
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                    'email'      => $user->user_email,
                    'discount'   => '',
                );
            } else {
                foreach ( $user_info as $key => $value ) {
                    if ( ! empty( $value ) ) {
                        continue;
                    }

                    switch( $key ) {
                        case 'user_id':
                            $user_info[ $key ] = $user->ID;
                            break;
                        case 'first_name':
                            $user_info[ $key ] = $user->first_name;
                            break;
                        case 'last_name':
                            $user_info[ $key ] = $user->last_name;
                            break;
                        case 'email':
                            $user_info[ $key ] = $user->user_email;
                            break;
                    }
                }
            }
        }

        return $user_info;
    }

    private function setup_invoice_key() {
        $key = $this->get_meta( '_wpinv_key', true );
        
        return $key;
    }

    private function setup_invoice_number() {
        $number = $this->get_meta( '_wpinv_number', true );

        if ( !$number ) {
            $number = wp_sprintf( __( 'WPINV-%d', 'invoicing' ), $this->ID );
        }

        return $number;
    }
    
    private function insert_invoice() {
        $invoice_title = '';

        if ($number = $this->get_number()) {
            $invoice_title = $number;
        } else if ( ! empty( $this->ID ) ) {
            $invoice_title = wp_sprintf( __( 'WPINV-%d', 'invoicing' ), $this->ID );
        } else {
            $invoice_title = __( 'WPINV-', 'invoicing' );
        }

        if ( empty( $this->key ) ) {
            $this->key = self::generate_key();
            $this->pending['key'] = $this->key;
        }

        if ( empty( $this->ip ) ) {
            $this->ip = wpinv_get_ip();
            $this->pending['ip'] = $this->ip;
        }
        
        $payment_data = array(
            'price'        => $this->total,
            'date'         => $this->date,
            'user_email'   => $this->email,
            'invoice_key'  => $this->key,
            'currency'     => $this->currency,
            'items'        => $this->items,
            'user_info' => array(
                'user_id'    => $this->user_id,
                'email'      => $this->email,
                'first_name' => $this->first_name,
                'last_name'  => $this->last_name,
                'address'    => $this->address,
                'phone'      => $this->phone,
                'city'       => $this->city,
                'country'    => $this->country,
                'state'      => $this->state,
                'zip'        => $this->zip,
                'company'    => $this->company,
                'vat_number' => $this->vat_number,
                'discount'   => $this->discounts,
            ),
            'cart_details' => $this->cart_details,
            'status'       => $this->status,
            'fees'         => $this->fees,
        );
        
        $post_name      = sanitize_title( $invoice_title );

        $post_data = array(
                        'post_title'    => $invoice_title,
                        'post_status'   => $this->status,
                        'post_type'     => 'wpi_invoice',
                        'post_date'     => ! empty( $this->date ) && $this->date != '0000-00-00 00:00:00' ? $this->date : current_time( 'mysql' ),
                        'post_date_gmt' => ! empty( $this->date ) && $this->date != '0000-00-00 00:00:00' ? get_gmt_from_date( $this->date ) : current_time( 'mysql', 1 ),
                        'post_parent'   => $this->parent_invoice,
                    );
        $args = apply_filters( 'wpinv_insert_invoice_args', $post_data, $this );

        // Create a blank invoice
        if ( !empty( $this->ID ) ) {
            $args['ID']         = $this->ID;
            $args['post_name']  = $post_name;
            
            $invoice_id = wp_update_post( $args );
        } else {
            $invoice_id = wp_insert_post( $args );
            
            $post_title = wp_sprintf( __( 'WPINV-%d', 'invoicing' ), $invoice_id );
            global $wpdb;
            $wpdb->update( $wpdb->posts, array( 'post_title' => $post_title, 'post_name' => sanitize_title( $post_title ) ), array( 'ID' => $invoice_id ) );
            clean_post_cache( $invoice_id );
        }

        if ( !empty( $invoice_id ) ) {             
            $this->ID  = $invoice_id;
            $this->_ID = $invoice_id;
            
            ///$this->pending['user_id'] = $this->user_id;
            if ( isset( $this->pending['number'] ) ) {
                $this->pending['number'] = $post_name;
            }
            
            $this->payment_meta = apply_filters( 'wpinv_payment_meta', $this->payment_meta, $payment_data );
            if ( ! empty( $this->payment_meta['fees'] ) ) {
                $this->fees = array_merge( $this->fees, $this->payment_meta['fees'] );
                foreach( $this->fees as $fee ) {
                    $this->increase_fees( $fee['amount'] );
                }
            }

            $this->update_meta( '_wpinv_payment_meta', $this->payment_meta );            
            $this->new = true;
        }

        return $this->ID;
    }

    public function save( $setup = false ) {
        global $wpi_session;
        
        $saved = false;
        
        if ( empty( $this->key ) ) {
            $this->key = self::generate_key();
            $this->pending['key'] = $this->key;
        }
        
        if ( empty( $this->ID ) ) {
            $invoice_id = $this->insert_invoice();

            if ( false === $invoice_id ) {
                $saved = false;
            } else {
                $this->ID = $invoice_id;
            }
        }        

        // If we have something pending, let's save it
        if ( !empty( $this->pending ) ) {
            $total_increase = 0;
            $total_decrease = 0;

            foreach ( $this->pending as $key => $value ) {
                switch( $key ) {
                    case 'items':
                        // Update totals for pending items
                        foreach ( $this->pending[ $key ] as $item ) {
                            switch( $item['action'] ) {
                                case 'add':
                                    $price = $item['price'];
                                    $taxes = $item['tax'];

                                    if ( 'publish' === $this->status || 'complete' === $this->status || 'revoked' === $this->status ) {
                                        $total_increase += $price;
                                    }
                                    break;

                                case 'remove':
                                    if ( 'publish' === $this->status || 'complete' === $this->status || 'revoked' === $this->status ) {
                                        $total_decrease += $item['price'];
                                    }
                                    break;
                            }
                        }
                        break;
                    case 'fees':
                        if ( 'publish' !== $this->status && 'complete' !== $this->status && 'revoked' !== $this->status ) {
                            break;
                        }

                        if ( empty( $this->pending[ $key ] ) ) {
                            break;
                        }

                        foreach ( $this->pending[ $key ] as $fee ) {
                            switch( $fee['action'] ) {
                                case 'add':
                                    $total_increase += $fee['amount'];
                                    break;

                                case 'remove':
                                    $total_decrease += $fee['amount'];
                                    break;
                            }
                        }
                        break;
                    case 'status':
                        $this->update_status( $this->status );
                        break;
                    case 'gateway':
                        $this->update_meta( '_wpinv_gateway', $this->gateway );
                        break;
                    case 'mode':
                        $this->update_meta( '_wpinv_mode', $this->mode );
                        break;
                    case 'transaction_id':
                        $this->update_meta( '_wpinv_transaction_id', $this->transaction_id );
                        break;
                    case 'ip':
                        $this->update_meta( '_wpinv_user_ip', $this->ip );
                        break;
                    ///case 'user_id':
                        ///$this->update_meta( '_wpinv_user_id', $this->user_id );
                        ///$this->user_info['user_id'] = $this->user_id;
                        ///break;
                    case 'first_name':
                        $this->update_meta( '_wpinv_first_name', $this->first_name );
                        $this->user_info['first_name'] = $this->first_name;
                        break;
                    case 'last_name':
                        $this->update_meta( '_wpinv_last_name', $this->last_name );
                        $this->user_info['last_name'] = $this->last_name;
                        break;
                    ///case 'email':
                        ///$this->update_meta( '_wpinv_email', $this->email );
                        ///$this->user_info['email'] = $this->email;
                        ///break;
                    case 'phone':
                        $this->update_meta( '_wpinv_phone', $this->phone );
                        $this->user_info['phone'] = $this->phone;
                        break;
                    case 'address':
                        $this->update_meta( '_wpinv_address', $this->address );
                        $this->user_info['address'] = $this->address;
                        break;
                    case 'city':
                        $this->update_meta( '_wpinv_city', $this->city );
                        $this->user_info['city'] = $this->city;
                        break;
                    case 'country':
                        $this->update_meta( '_wpinv_country', $this->country );
                        $this->user_info['country'] = $this->country;
                        break;
                    case 'state':
                        $this->update_meta( '_wpinv_state', $this->state );
                        $this->user_info['state'] = $this->state;
                        break;
                    case 'zip':
                        $this->update_meta( '_wpinv_zip', $this->zip );
                        $this->user_info['zip'] = $this->zip;
                        break;
                    case 'company':
                        $this->update_meta( '_wpinv_company', $this->company );
                        $this->user_info['company'] = $this->company;
                        break;
                    case 'vat_number':
                        $this->update_meta( '_wpinv_vat_number', $this->vat_number );
                        $this->user_info['vat_number'] = $this->vat_number;
                        
                        $vat_info = $wpi_session->get( 'user_vat_info' );
                        if ( $this->vat_number && !empty( $vat_info ) && isset( $vat_info['number'] ) && isset( $vat_info['valid'] ) && $vat_info['number'] == $this->vat_number ) {
                            $this->update_meta( '_wpinv_self_certified', (bool)$vat_info['valid'] );
                            $this->user_info['self_certified'] = (bool)$vat_info['valid'];
                        }
                        
                        break;
                    case 'vat_rate':
                        $this->update_meta( '_wpinv_vat_rate', $this->vat_rate );
                        $this->user_info['vat_rate'] = $this->vat_rate;
                        break;
                    case 'self_certified':
                        $this->update_meta( '_wpinv_self_certified', $this->self_certified );
                        $this->user_info['self_certified'] = $this->self_certified;
                        break;
                    
                    case 'key':
                        $this->update_meta( '_wpinv_key', $this->key );
                        break;
                    case 'number':
                        $this->update_meta( '_wpinv_number', $this->number );
                        break;
                    case 'date':
                        $args = array(
                            'ID'        => $this->ID,
                            'post_date' => $this->date,
                            'edit_date' => true,
                        );

                        wp_update_post( $args );
                        break;
                    case 'completed_date':
                        $this->update_meta( '_wpinv_completed_date', $this->completed_date );
                        break;
                    case 'discounts':
                        if ( ! is_array( $this->discounts ) ) {
                            $this->discounts = explode( ',', $this->discounts );
                        }

                        $this->user_info['discount'] = implode( ',', $this->discounts );
                        break;
                        
                    //case 'tax':
                        //$this->update_meta( '_wpinv_tax', wpinv_format_amount( $this->tax, NULL, true ) );
                        //break;
                    case 'discount':
                        $this->update_meta( '_wpinv_discount', wpinv_format_amount( $this->discount, NULL, true ) );
                        break;
                    case 'discount_code':
                        $this->update_meta( '_wpinv_discount_code', $this->discount_code );
                        break;
                    //case 'fees':
                        //$this->update_meta( '_wpinv_fees', $this->fees );
                        //break;
                    case 'parent_invoice':
                        $args = array(
                            'ID'          => $this->ID,
                            'post_parent' => $this->parent_invoice,
                        );
                        wp_update_post( $args );
                        break;
                    default:
                        do_action( 'wpinv_save', $this, $key );
                        break;
                }
            }       

            $this->update_meta( '_wpinv_subtotal', wpinv_format_amount( $this->subtotal, NULL, true ) );
            $this->update_meta( '_wpinv_total', wpinv_format_amount( $this->total, NULL, true ) );
            $this->update_meta( '_wpinv_tax', wpinv_format_amount( $this->tax, NULL, true ) );
            
            $this->items    = array_values( $this->items );
            
            $new_meta = array(
                'items'         => $this->items,
                'cart_details'  => $this->cart_details,
                'fees'          => $this->fees,
                'currency'      => $this->currency,
                'user_info'     => $this->user_info,
            );
            
            $meta        = $this->get_meta();
            $merged_meta = array_merge( $meta, $new_meta );

            // Only save the payment meta if it's changed
            if ( md5( serialize( $meta ) ) !== md5( serialize( $merged_meta) ) ) {
                $updated     = $this->update_meta( '_wpinv_payment_meta', $merged_meta );
                if ( false !== $updated ) {
                    $saved = true;
                }
            }

            $this->pending = array();
            $saved         = true;
        } else {
            $this->update_meta( '_wpinv_subtotal', wpinv_format_amount( $this->subtotal, NULL, true ) );
            $this->update_meta( '_wpinv_total', wpinv_format_amount( $this->total, NULL, true ) );
            $this->update_meta( '_wpinv_tax', wpinv_format_amount( $this->tax, NULL, true ) );
        }

        if ( true === $saved || $setup ) {
            $this->setup_invoice( $this->ID );
        }
        
        return $saved;
    }
    
    public function add_fee( $args, $global = true ) {
        $default_args = array(
            'label'       => '',
            'amount'      => 0,
            'type'        => 'fee',
            'id'          => '',
            'no_tax'      => false,
            'item_id'     => 0,
        );

        $fee = wp_parse_args( $args, $default_args );
        
        if ( !empty( $fee['label'] ) ) {
            return false;
        }
        
        $fee['id']  = sanitize_title( $fee['label'] );
        
        $this->fees[]               = $fee;
        
        $added_fee               = $fee;
        $added_fee['action']     = 'add';
        $this->pending['fees'][] = $added_fee;
        reset( $this->fees );

        $this->increase_fees( $fee['amount'] );
        return true;
    }

    public function remove_fee( $key ) {
        $removed = false;

        if ( is_numeric( $key ) ) {
            $removed = $this->remove_fee_by( 'index', $key );
        }

        return $removed;
    }

    public function remove_fee_by( $key, $value, $global = false ) {
        $allowed_fee_keys = apply_filters( 'wpinv_fee_keys', array(
            'index', 'label', 'amount', 'type',
        ) );

        if ( ! in_array( $key, $allowed_fee_keys ) ) {
            return false;
        }

        $removed = false;
        if ( 'index' === $key && array_key_exists( $value, $this->fees ) ) {
            $removed_fee             = $this->fees[ $value ];
            $removed_fee['action']   = 'remove';
            $this->pending['fees'][] = $removed_fee;

            $this->decrease_fees( $removed_fee['amount'] );

            unset( $this->fees[ $value ] );
            $removed = true;
        } else if ( 'index' !== $key ) {
            foreach ( $this->fees as $index => $fee ) {
                if ( isset( $fee[ $key ] ) && $fee[ $key ] == $value ) {
                    $removed_fee             = $fee;
                    $removed_fee['action']   = 'remove';
                    $this->pending['fees'][] = $removed_fee;

                    $this->decrease_fees( $removed_fee['amount'] );

                    unset( $this->fees[ $index ] );
                    $removed = true;

                    if ( false === $global ) {
                        break;
                    }
                }
            }
        }

        if ( true === $removed ) {
            $this->fees = array_values( $this->fees );
        }

        return $removed;
    }

    

    public function add_note( $note = '', $customer_type = false, $added_by_user = false ) {
        // Bail if no note specified
        if( !$note ) {
            return false;
        }

        if ( empty( $this->ID ) )
            return false;
        
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            $user                 = get_user_by( 'id', get_current_user_id() );
            $comment_author       = $user->display_name;
            $comment_author_email = $user->user_email;
        } else {
            $comment_author       = __( 'GeoDirectory', 'invoicing' );
            $comment_author_email = strtolower( __( 'GeoDirectory', 'invoicing' ) ) . '@';
            $comment_author_email .= isset( $_SERVER['HTTP_HOST'] ) ? str_replace( 'www.', '', $_SERVER['HTTP_HOST'] ) : 'noreply.com';
            $comment_author_email = sanitize_email( $comment_author_email );
        }

        do_action( 'wpinv_pre_insert_invoice_note', $this->ID, $note, $customer_type );

        $note_id = wp_insert_comment( wp_filter_comment( array(
            'comment_post_ID'      => $this->ID,
            'comment_content'      => $note,
            'comment_agent'        => 'GeoDirectory',
            'user_id'              => is_admin() ? get_current_user_id() : 0,
            'comment_date'         => current_time( 'mysql' ),
            'comment_date_gmt'     => current_time( 'mysql', 1 ),
            'comment_approved'     => 1,
            'comment_parent'       => 0,
            'comment_author'       => $comment_author,
            'comment_author_IP'    => wpinv_get_ip(),
            'comment_author_url'   => '',
            'comment_author_email' => $comment_author_email,
            'comment_type'         => 'wpinv_note'
        ) ) );

        do_action( 'wpinv_insert_payment_note', $note_id, $this->ID, $note );
        
        if ( $customer_type ) {
            add_comment_meta( $note_id, 'wpinv_customer_note', 1 );

            do_action( 'wpinv_new_customer_note', array( 'invoice_id' => $this->ID, 'user_note' => $note ) );
        }

        return $note_id;
    }

    private function increase_subtotal( $amount = 0.00 ) {
        $amount          = (float) $amount;
        $this->subtotal += $amount;
        $this->subtotal  = wpinv_format_amount( $this->subtotal, NULL, true );

        $this->recalculate_total();
    }

    private function decrease_subtotal( $amount = 0.00 ) {
        $amount          = (float) $amount;
        $this->subtotal -= $amount;
        $this->subtotal  = wpinv_format_amount( $this->subtotal, NULL, true );

        if ( $this->subtotal < 0 ) {
            $this->subtotal = 0;
        }

        $this->recalculate_total();
    }

    private function increase_fees( $amount = 0.00 ) {
        $amount            = (float)$amount;
        $this->fees_total += $amount;
        $this->fees_total  = wpinv_format_amount( $this->fees_total, NULL, true );

        $this->recalculate_total();
    }

    private function decrease_fees( $amount = 0.00 ) {
        $amount            = (float) $amount;
        $this->fees_total -= $amount;
        $this->fees_total  = wpinv_format_amount( $this->fees_total, NULL, true );

        if ( $this->fees_total < 0 ) {
            $this->fees_total = 0;
        }

        $this->recalculate_total();
    }

    public function recalculate_total() {
        global $wpi_nosave;
        
        $this->total = $this->subtotal + $this->tax + $this->fees_total;
        $this->total = wpinv_format_amount( $this->total, NULL, true );
        
        do_action( 'wpinv_invoice_recalculate_total', $this, $wpi_nosave );
    }
    
    public function increase_tax( $amount = 0.00 ) {
        $amount       = (float) $amount;
        $this->tax   += $amount;

        $this->recalculate_total();
    }

    public function decrease_tax( $amount = 0.00 ) {
        $amount     = (float) $amount;
        $this->tax -= $amount;

        if ( $this->tax < 0 ) {
            $this->tax = 0;
        }

        $this->recalculate_total();
    }

    public function update_status( $new_status = false, $note = '', $manual = false ) {
        $old_status = ! empty( $this->old_status ) ? $this->old_status : get_post_status( $this->ID );
        
        if ( $old_status === $new_status && in_array( $new_status, array_keys( wpinv_get_invoice_statuses() ) ) ) {
            return false; // Don't permit status changes that aren't changes
        }

        $do_change = apply_filters( 'wpinv_should_update_invoice_status', true, $this->ID, $new_status, $old_status );
        $updated = false;

        if ( $do_change ) {
            do_action( 'wpinv_before_invoice_status_change', $this->ID, $new_status, $old_status );

            $update_post_data                   = array();
            $update_post_data['ID']             = $this->ID;
            $update_post_data['post_status']    = $new_status;
            $update_post_data['edit_date']      = current_time( 'mysql', 0 );
            $update_post_data['edit_date_gmt']  = current_time( 'mysql', 1 );
            
            $update_post_data = apply_filters( 'wpinv_update_invoice_status_fields', $update_post_data, $this->ID );

            $updated = wp_update_post( $update_post_data );     
           
            // Process any specific status functions
            switch( $new_status ) {
                case 'refunded':
                    $this->process_refund();
                    break;
                case 'failed':
                    $this->process_failure();
                    break;
                case 'pending':
                    $this->process_pending();
                    break;
            }
            
            // Status was changed.
            do_action( 'wpinv_status_' . $new_status, $this->ID, $old_status );
            do_action( 'wpinv_status_' . $old_status . '_to_' . $new_status, $this->ID, $old_status );
            wpinv_error_log( 'wpinv_status_' . $old_status . '_to_' . $new_status, 'update_status', __FILE__, __LINE__ );
            do_action( 'wpinv_update_status', $this->ID, $new_status, $old_status );
        }

        return $updated;
    }

    public function refund() {
        $this->old_status        = $this->status;
        $this->status            = 'refunded';
        $this->pending['status'] = $this->status;

        $this->save();
    }

    public function update_meta( $meta_key = '', $meta_value = '', $prev_value = '' ) {
        if ( empty( $meta_key ) ) {
            return false;
        }

        if ( $meta_key == 'key' || $meta_key == 'date' ) {

            $current_meta = $this->get_meta();
            $current_meta[ $meta_key ] = $meta_value;

            $meta_key     = '_wpinv_payment_meta';
            $meta_value   = $current_meta;

        } ///else if ( $meta_key == 'email' || $meta_key == '_wpinv_email' ) {

            /*$meta_value = apply_filters( 'wpinv_update_payment_meta_' . $meta_key, $meta_value, $this->ID );
            
            update_post_meta( $this->ID, '_wpinv_email', $meta_value );

            $current_meta = $this->get_meta();
            $current_meta['user_info']['email']  = $meta_value;

            $meta_key     = '_wpinv_payment_meta';
            $meta_value   = $current_meta;
        }*/
        ///

        $meta_value = apply_filters( 'wpinv_update_payment_meta_' . $meta_key, $meta_value, $this->ID );
        
        return update_post_meta( $this->ID, $meta_key, $meta_value, $prev_value );
    }

    private function process_refund() {
        $process_refund = true;

        // If the payment was not in publish or revoked status, don't decrement stats as they were never incremented
        if ( ( 'publish' != $this->old_status && 'revoked' != $this->old_status ) || 'refunded' != $this->status ) {
            $process_refund = false;
        }

        // Allow extensions to filter for their own payment types, Example: Recurring Payments
        $process_refund = apply_filters( 'wpinv_should_process_refund', $process_refund, $this );

        if ( false === $process_refund ) {
            return;
        }

        do_action( 'wpinv_pre_refund_invoice', $this );
        
        $decrease_store_earnings = apply_filters( 'wpinv_decrease_store_earnings_on_refund', true, $this );
        $decrease_customer_value = apply_filters( 'wpinv_decrease_customer_value_on_refund', true, $this );
        $decrease_purchase_count = apply_filters( 'wpinv_decrease_customer_purchase_count_on_refund', true, $this );
        
        do_action( 'wpinv_post_refund_invoice', $this );
    }

    private function process_failure() {
        $discounts = $this->discounts;
        if ( empty( $discounts ) ) {
            return;
        }

        if ( ! is_array( $discounts ) ) {
            $discounts = array_map( 'trim', explode( ',', $discounts ) );
        }

        foreach ( $discounts as $discount ) {
            wpinv_decrease_discount_usage( $discount );
        }
    }
    
    private function process_pending() {
        $process_pending = true;

        // If the payment was not in publish or revoked status, don't decrement stats as they were never incremented
        if ( ( 'publish' != $this->old_status && 'revoked' != $this->old_status ) || 'pending' != $this->status ) {
            $process_pending = false;
        }

        // Allow extensions to filter for their own payment types, Example: Recurring Payments
        $process_pending = apply_filters( 'wpinv_should_process_pending', $process_pending, $this );

        if ( false === $process_pending ) {
            return;
        }

        $decrease_store_earnings = apply_filters( 'wpinv_decrease_store_earnings_on_pending', true, $this );
        $decrease_customer_value = apply_filters( 'wpinv_decrease_customer_value_on_pending', true, $this );
        $decrease_purchase_count = apply_filters( 'wpinv_decrease_customer_purchase_count_on_pending', true, $this );

        $this->completed_date = false;
        $this->update_meta( '_wpinv_completed_date', '' );
    }
    
    // get data
    public function get_meta( $meta_key = '_wpinv_payment_meta', $single = true ) {
        $meta = get_post_meta( $this->ID, $meta_key, $single );

        if ( $meta_key === '_wpinv_payment_meta' ) {
            if ( empty( $meta['key'] ) ) {
                $meta['key'] = $this->setup_invoice_key();
            }

            ///if ( empty( $meta['email'] ) ) {
                ///$meta['email'] = $this->setup_email();
            ///}

            if ( empty( $meta['date'] ) ) {
                $meta['date'] = get_post_field( 'post_date', $this->ID );
            }
        }

        $meta = apply_filters( 'wpinv_get_invoice_meta_' . $meta_key, $meta, $this->ID );

        return apply_filters( 'wpinv_get_invoice_meta', $meta, $this->ID, $meta_key );
    }
    
    public function get_description() {
        $post = get_post( $this->ID );
        
        $description = !empty( $post ) ? $post->post_content : '';
        return apply_filters( 'wpinv_get_description', $description, $this->ID, $this );
    }
    
    public function get_status( $nicename = false ) {
        if ( !$nicename ) {
            $status = $this->status;
        } else {
            $status = $this->status_nicename;
        }
        
        return apply_filters( 'wpinv_get_status', $status, $nicename, $this->ID, $this );
    }
    
    public function get_cart_details() {
        return apply_filters( 'wpinv_cart_details', $this->cart_details, $this->ID, $this );
    }
    
    public function get_total( $currency = false ) {        
        $total = wpinv_format_amount( $this->total, NULL, !$currency );
        if ( $currency ) {
            $total = wpinv_price( $total, $this->get_currency() );
        }
        
        return apply_filters( 'wpinv_get_invoice_total', $total, $this->ID, $this, $currency );
    }
    
    public function get_subtotal( $currency = false ) {
        $subtotal = wpinv_format_amount( $this->subtotal, NULL, !$currency );
        
        if ( $currency ) {
            $subtotal = wpinv_price( $subtotal, $this->get_currency() );
        }
        
        return apply_filters( 'wpinv_get_invoice_subtotal', $subtotal, $this->ID, $this, $currency );
    }
    
    public function get_discounts( $array = false ) {
        $discounts = $this->discounts;
        if ( $array && $discounts ) {
            $discounts = explode( ',', $discounts );
        }
        return apply_filters( 'wpinv_payment_discounts', $discounts, $this->ID, $this, $array );
    }
    
    public function get_discount( $currency = false, $dash = false ) {
        if ( !empty( $this->discounts ) ) {
            global $ajax_cart_details;
            $ajax_cart_details = $this->get_cart_details();

            $this->discount = wpinv_get_cart_items_discount_amount( $this->items , $this->discounts );
        }
        $discount   = wpinv_format_amount( $this->discount, NULL, !$currency );
        $dash       = $dash && $discount > 0 ? '&ndash;' : '';
        
        if ( $currency ) {
            $discount = wpinv_price( $discount, $this->get_currency() );
        }
        
        $discount   = $dash . $discount;
        
        return apply_filters( 'wpinv_get_invoice_discount', $discount, $this->ID, $this, $currency, $dash );
    }
    
    public function get_discount_code() {
        return $this->discount_code;
    }
    
    public function get_tax( $currency = false ) {
        $tax = wpinv_format_amount( $this->tax, NULL, !$currency );
        
        if ( $currency ) {
            $tax = wpinv_price( $tax, $this->get_currency() );
        }
        
        return apply_filters( 'wpinv_get_invoice_tax', $tax, $this->ID, $this, $currency );
    }
    
    public function get_fees( $type = 'all' ) {
        $fees    = array();

        if ( ! empty( $this->fees ) && is_array( $this->fees ) ) {
            foreach ( $this->fees as $fee ) {
                if( 'all' != $type && ! empty( $fee['type'] ) && $type != $fee['type'] ) {
                    continue;
                }

                $fee['label'] = stripslashes( $fee['label'] );
                $fee['amount_display'] = wpinv_price( $fee['amount'], $this->get_currency() );
                $fees[]    = $fee;
            }
        }

        return apply_filters( 'wpinv_get_invoice_fees', $fees, $this->ID, $this );
    }
    
    public function get_fees_total( $type = 'all' ) {
        $fees_total = (float) 0.00;

        $payment_fees = isset( $this->payment_meta['fees'] ) ? $this->payment_meta['fees'] : array();
        if ( ! empty( $payment_fees ) ) {
            foreach ( $payment_fees as $fee ) {
                $fees_total += (float) $fee['amount'];
            }
        }

        return apply_filters( 'wpinv_get_invoice_fees_total', $fees_total, $this->ID, $this );
        /*
        $fees = $this->get_fees( $type );

        $fees_total = 0;
        if ( ! empty( $fees ) && is_array( $fees ) ) {
            foreach ( $fees as $fee_id => $fee ) {
                if( 'all' != $type && !empty( $fee['type'] ) && $type != $fee['type'] ) {
                    continue;
                }

                $fees_total += $fee['amount'];
            }
        }

        return apply_filters( 'wpinv_get_invoice_fees_total', $fees_total, $this->ID, $this );
        */
    }

    public function get_user_id() {
        return apply_filters( 'wpinv_user_id', $this->user_id, $this->ID, $this );
    }
    
    public function get_first_name() {
        return apply_filters( 'wpinv_first_name', $this->first_name, $this->ID, $this );
    }
    
    public function get_last_name() {
        return apply_filters( 'wpinv_last_name', $this->last_name, $this->ID, $this );
    }
    
    public function get_user_full_name() {
        return apply_filters( 'wpinv_user_full_name', $this->full_name, $this->ID, $this );
    }
    
    public function get_user_info() {
        return apply_filters( 'wpinv_user_info', $this->user_info, $this->ID, $this );
    }
    
    public function get_email() {
        return apply_filters( 'wpinv_user_email', $this->email, $this->ID, $this );
    }
    
    public function get_address() {
        return apply_filters( 'wpinv_address', $this->address, $this->ID, $this );
    }
    
    public function get_number() {
        return apply_filters( 'wpinv_number', $this->number, $this->ID, $this );
    }
    
    public function get_items() {
        return apply_filters( 'wpinv_payment_meta_items', $this->items, $this->ID, $this );
    }
    
    public function get_key() {
        return apply_filters( 'wpinv_key', $this->key, $this->ID, $this );
    }
    
    public function get_transaction_id() {
        return apply_filters( 'wpinv_get_invoice_transaction_id', $this->transaction_id, $this->ID, $this );
    }
    
    public function get_gateway() {
        return apply_filters( 'wpinv_gateway', $this->gateway, $this->ID, $this );
    }
    
    public function get_gateway_title() {
        $this->gateway_title = !empty( $this->gateway_title ) ? $this->gateway_title : wpinv_get_gateway_checkout_label( $this->gateway );
        
        return apply_filters( 'wpinv_gateway_title', $this->gateway_title, $this->ID, $this );
    }
    
    public function get_currency() {
        return apply_filters( 'wpinv_currency_code', $this->currency, $this->ID, $this );
    }
    
    public function get_created_date() {
        return apply_filters( 'wpinv_created_date', $this->date, $this->ID, $this );
    }
    
    public function get_completed_date() {
        return apply_filters( 'wpinv_completed_date', $this->completed_date, $this->ID, $this );
    }
    
    public function get_invoice_date( $formatted = true ) {
        $date_completed = $this->completed_date;
        $invoice_date   = $date_completed != '' && $date_completed != '0000-00-00 00:00:00' ? $date_completed : '';
        
        if ( $invoice_date == '' ) {
            $date_created   = $this->date;
            $invoice_date   = $date_created != '' && $date_created != '0000-00-00 00:00:00' ? $date_created : '';
        }
        
        if ( $formatted && $invoice_date ) {
            $invoice_date   = date_i18n( get_option( 'date_format' ), strtotime( $invoice_date ) );
        }

        return apply_filters( 'wpinv_get_invoice_date', $invoice_date, $formatted, $this->ID, $this );
    }
    
    public function get_ip() {
        return apply_filters( 'wpinv_user_ip', $this->ip, $this->ID, $this );
    }
        
    public function has_status( $status ) {
        return apply_filters( 'wpinv_has_status', ( is_array( $status ) && in_array( $this->get_status(), $status ) ) || $this->get_status() === $status ? true : false, $this, $status );
    }
    
    public function add_item( $item_id = 0, $args = array() ) {
        global $wpi_current_id, $wpi_item_id;
        
        $item = new WPInv_Item( $item_id );

        // Bail if this post isn't a item
        if( !$item || $item->post_type !== 'wpi_item' ) {
            return false;
        }
        
        $has_quantities = wpinv_item_quantities_enabled();

        // Set some defaults
        $defaults = array(
            'quantity'  => 1,
            'id'        => false,
            'name'      => $item->get_name(),
            'item_price'=> false,
            'discount'  => 0,
            'tax'       => 0.00,
            'meta'      => array(),
            'fees'      => array()
        );

        $args = wp_parse_args( apply_filters( 'wpinv_add_item_args', $args, $item->ID ), $defaults );
        $args['quantity']   = $has_quantities && $args['quantity'] > 0 ? absint( $args['quantity'] ) : 1;

        $wpi_current_id         = $this->ID;
        $wpi_item_id            = $item->ID;
        $discounts              = $this->get_discounts();
        
        $_POST['wpinv_country'] = $this->country;
        $_POST['wpinv_state']   = $this->state;
        
        if ($has_quantities) {
            $this->cart_details = !empty( $this->cart_details ) ? array_values( $this->cart_details ) : $this->cart_details;
            
            foreach ( $this->items as $key => $cart_item ) {
                if ( (int)$item_id !== (int)$cart_item['id'] ) {
                    continue;
                }

                $this->items[ $key ]['quantity'] += $args['quantity'];
                break;
            }
            
            $found_cart_key = false;
            foreach ( $this->cart_details as $cart_key => $cart_item ) {
                if ( $item_id != $cart_item['id'] ) {
                    continue;
                }

                $found_cart_key = $cart_key;
                break;
            }
        }
        
        if ($has_quantities && $found_cart_key !== false) {
            $cart_item          = $this->cart_details[$found_cart_key];
            $item_price         = $cart_item['item_price'];
            $quantity           = !empty( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1;
            $tax_rate           = !empty( $cart_item['vat_rate'] ) ? $cart_item['vat_rate'] : 0;
            
            $new_quantity       = $quantity + $args['quantity'];
            $subtotal           = $item_price * $new_quantity;
            
            $args['quantity']   = $new_quantity;
            $discount           = !empty( $args['discount'] ) ? $args['discount'] : 0;
            $tax                = $subtotal > 0 && $tax_rate > 0 ? ( ( $subtotal - $discount ) * 0.01 * (float)$tax_rate ) : 0;
            
            $discount_increased = $discount > 0 && $subtotal > 0 && $discount > (float)$cart_item['discount'] ? $discount - (float)$cart_item['discount'] : 0;
            $tax_increased      = $tax > 0 && $subtotal > 0 && $tax > (float)$cart_item['tax'] ? $tax - (float)$cart_item['tax'] : 0;
            // The total increase equals the number removed * the item_price
            $total_increased    = wpinv_format_amount( $item_price, NULL, true );
            
            if ( wpinv_prices_include_tax() ) {
                $subtotal -= wpinv_format_amount( $tax, NULL, true );
            }

            $total              = $subtotal - $discount + $tax;

            // Do not allow totals to go negative
            if( $total < 0 ) {
                $total = 0;
            }
            
            $cart_item['quantity']  = $new_quantity;
            $cart_item['subtotal']  = $subtotal;
            $cart_item['discount']  = $discount;
            $cart_item['tax']       = $tax;
            $cart_item['price']     = $total;
            
            $subtotal               = $total_increased - $discount_increased;
            $tax                    = $tax_increased;
            
            $this->cart_details[$found_cart_key] = $cart_item;
        } else {
            // Allow overriding the price
            if( false !== $args['item_price'] ) {
                $item_price = $args['item_price'];
            } else {
                $item_price = wpinv_get_item_price( $item->ID );
            }

            // Sanitizing the price here so we don't have a dozen calls later
            $item_price = wpinv_sanitize_amount( $item_price );
            $subtotal   = wpinv_format_amount( $item_price * $args['quantity'], NULL, true );
        
            $discount   = !empty( $args['discount'] ) ? $args['discount'] : 0;
            $tax_class  = !empty( $args['vat_class'] ) ? $args['vat_class'] : '';
            $tax_rate   = !empty( $args['vat_rate'] ) ? $args['vat_rate'] : 0;
            $tax        = $subtotal > 0 && $tax_rate > 0 ? ( ( $subtotal - $discount ) * 0.01 * (float)$tax_rate ) : 0;

            // Setup the items meta item
            $new_item = array(
                'id'       => $item->ID,
                'quantity' => $args['quantity'],
            );

            $this->items[]  = $new_item;

            if ( wpinv_prices_include_tax() ) {
                $subtotal -= wpinv_format_amount( $tax, NULL, true );
            }

            $total      = $subtotal - $discount + $tax;

            // Do not allow totals to go negative
            if( $total < 0 ) {
                $total = 0;
            }
        
            $this->cart_details[] = array(
                'name'        => !empty($args['name']) ? $args['name'] : $item->get_name(),
                'id'          => $item->ID,
                'item_price'  => wpinv_format_amount( $item_price, NULL, true ),
                'quantity'    => $args['quantity'],
                'discount'    => $discount,
                'subtotal'    => wpinv_format_amount( $subtotal, NULL, true ),
                'tax'         => wpinv_format_amount( $tax, NULL, true ),
                'price'       => wpinv_format_amount( $total, NULL, true ),
                'vat_rate'    => $tax_rate,
                'vat_class'   => $tax_class,
                'meta'        => $args['meta'],
                'fees'        => $args['fees'],
            );
                        
            $subtotal = $subtotal - $discount;
        }
        
        $added_item = end( $this->cart_details );
        $added_item['action']  = 'add';
        
        $this->pending['items'][] = $added_item;
        
        $this->increase_subtotal( $subtotal );
        $this->increase_tax( $tax );

        return true;
    }
    
    public function remove_item( $item_id, $args = array() ) {
        // Set some defaults
        $defaults = array(
            'quantity'   => 1,
            'item_price' => false,
            'cart_index' => false,
        );
        $args = wp_parse_args( $args, $defaults );

        // Bail if this post isn't a item
        if ( get_post_type( $item_id ) !== 'wpi_item' ) {
            return false;
        }
        
        $this->cart_details = !empty( $this->cart_details ) ? array_values( $this->cart_details ) : $this->cart_details;

        foreach ( $this->items as $key => $item ) {
            if ( !empty($item['id']) && (int)$item_id !== (int)$item['id'] ) {
                continue;
            }

            if ( false !== $args['cart_index'] ) {
                $cart_index = absint( $args['cart_index'] );
                $cart_item  = ! empty( $this->cart_details[ $cart_index ] ) ? $this->cart_details[ $cart_index ] : false;

                if ( ! empty( $cart_item ) ) {
                    // If the cart index item isn't the same item ID, don't remove it
                    if ( !empty($cart_item['id']) && $cart_item['id'] != $item['id'] ) {
                        continue;
                    }
                }
            }

            $item_quantity = $this->items[ $key ]['quantity'];
            if ( $item_quantity > $args['quantity'] ) {
                $this->items[ $key ]['quantity'] -= $args['quantity'];
                break;
            } else {
                unset( $this->items[ $key ] );
                break;
            }
        }

        $found_cart_key = false;
        if ( false === $args['cart_index'] ) {
            foreach ( $this->cart_details as $cart_key => $item ) {
                if ( $item_id != $item['id'] ) {
                    continue;
                }

                if ( false !== $args['item_price'] ) {
                    if ( isset( $item['item_price'] ) && (float) $args['item_price'] != (float) $item['item_price'] ) {
                        continue;
                    }
                }

                $found_cart_key = $cart_key;
                break;
            }
        } else {
            $cart_index = absint( $args['cart_index'] );

            if ( ! array_key_exists( $cart_index, $this->cart_details ) ) {
                return false; // Invalid cart index passed.
            }

            if ( (int) $this->cart_details[ $cart_index ]['id'] > 0 && (int) $this->cart_details[ $cart_index ]['id'] !== (int) $item_id ) {
                return false; // We still need the proper Item ID to be sure.
            }

            $found_cart_key = $cart_index;
        }
        
        $cart_item  = $this->cart_details[$found_cart_key];
        $quantity   = !empty( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1;
        $discounts  = $this->get_discounts();
        
        if ( $quantity > $args['quantity'] ) {
            $item_price         = $cart_item['item_price'];
            $tax_rate           = !empty( $cart_item['vat_rate'] ) ? $cart_item['vat_rate'] : 0;
            
            $new_quantity       = max( $quantity - $args['quantity'], 1);
            $subtotal           = $item_price * $new_quantity;
            
            $args['quantity']   = $new_quantity;
            $discount           = !empty( $cart_item['discount'] ) ? $cart_item['discount'] : 0;
            $tax                = $subtotal > 0 && $tax_rate > 0 ? ( ( $subtotal - $discount ) * 0.01 * (float)$tax_rate ) : 0;
            
            $discount_decrease  = (float)$cart_item['discount'] > 0 && $quantity > 0 ? wpinv_format_amount( ( (float)$cart_item['discount'] / $quantity ), NULL, true ) : 0;
            $discount_decrease  = $discount > 0 && $subtotal > 0 && (float)$cart_item['discount'] > $discount ? (float)$cart_item['discount'] - $discount : $discount_decrease; 
            $tax_decrease       = (float)$cart_item['tax'] > 0 && $quantity > 0 ? wpinv_format_amount( ( (float)$cart_item['tax'] / $quantity ), NULL, true ) : 0;
            $tax_decrease       = $tax > 0 && $subtotal > 0 && (float)$cart_item['tax'] > $tax ? (float)$cart_item['tax'] - $tax : $tax_decrease;
            
            // The total increase equals the number removed * the item_price
            $total_decrease     = wpinv_format_amount( $item_price, NULL, true );
            
            if ( wpinv_prices_include_tax() ) {
                $subtotal -= wpinv_format_amount( $tax, NULL, true );
            }

            $total              = $subtotal - $discount + $tax;

            // Do not allow totals to go negative
            if( $total < 0 ) {
                $total = 0;
            }
            
            $cart_item['quantity']  = $new_quantity;
            $cart_item['subtotal']  = $subtotal;
            $cart_item['discount']  = $discount;
            $cart_item['tax']       = $tax;
            $cart_item['price']     = $total;
            
            $added_item             = $cart_item;
            $added_item['id']       = $item_id;
            $added_item['price']    = $total_decrease;
            $added_item['quantity'] = $args['quantity'];
            
            $subtotal_decrease      = $total_decrease - $discount_decrease;
            
            $this->cart_details[$found_cart_key] = $cart_item;
            
            $remove_item = end( $this->cart_details );
        } else {
            $item_price     = $cart_item['item_price'];
            $discount       = !empty( $cart_item['discount'] ) ? $cart_item['discount'] : 0;
            $tax            = !empty( $cart_item['tax'] ) ? $cart_item['tax'] : 0;
        
            $subtotal_decrease  = ( $item_price * $quantity ) - $discount;
            $tax_decrease       = $tax;

            unset( $this->cart_details[$found_cart_key] );
            
            $remove_item             = $args;
            $remove_item['id']       = $item_id;
            $remove_item['price']    = $subtotal_decrease;
            $remove_item['quantity'] = $args['quantity'];
        }
        
        $remove_item['action']      = 'remove';
        $this->pending['items'][]   = $remove_item;
               
        $this->decrease_subtotal( $subtotal_decrease );
        $this->decrease_tax( $tax_decrease );
        
        return true;
    }
    
    public function update_items($temp = false) {
        global $wpi_current_id, $wpi_item_id, $wpi_nosave;
        
        if ( !empty( $this->cart_details ) ) {
            $wpi_nosave             = $temp;
            $cart_subtotal          = 0;
            $cart_discount          = 0;
            $cart_tax               = 0;
            $update_cart_details    = array();
            
            $_POST['wpinv_country'] = $this->country;
            $_POST['wpinv_state']   = $this->state;
            
            foreach ( $this->cart_details as $key => $item ) {
                $item_price = $item['item_price'];
                $quantity   = wpinv_item_quantities_enabled() && $item['quantity'] > 0 ? absint( $item['quantity'] ) : 1;
                $amount     = wpinv_format_amount( $item_price * $quantity, NULL, true );
                $subtotal   = $item_price * $quantity;
                
                $wpi_current_id         = $this->ID;
                $wpi_item_id            = $item['id'];
                
                $discount   = wpinv_get_cart_item_discount_amount( $item, $this->get_discounts() );
                
                $tax_rate   = wpinv_get_tax_rate( $this->country, $this->state, $wpi_item_id );
                $tax_class  = wpinv_get_item_vat_class( $wpi_item_id );
                $tax        = $item_price > 0 ? ( ( $subtotal - $discount ) * 0.01 * (float)$tax_rate ) : 0;

                if ( wpinv_prices_include_tax() ) {
                    $subtotal -= wpinv_format_amount( $tax, NULL, true );
                }

                $total      = $subtotal - $discount + $tax;

                // Do not allow totals to go negative
                if( $total < 0 ) {
                    $total = 0;
                }

                $cart_details[] = array(
                    'id'          => $item['id'],
                    'name'        => $item['name'],
                    'item_price'  => wpinv_format_amount( $item_price, NULL, true ),
                    'quantity'    => $quantity,
                    'discount'    => $discount,
                    'subtotal'    => wpinv_format_amount( $subtotal, NULL, true ),
                    'tax'         => wpinv_format_amount( $tax, NULL, true ),
                    'price'       => wpinv_format_amount( $total, NULL, true ),
                    'vat_rate'    => $tax_rate,
                    'vat_class'   => $tax_class,
                    'meta'        => isset($item['meta']) ? $item['meta'] : array(),
                    'fees'        => isset($item['fees']) ? $item['fees'] : array(),
                );
                
                $cart_subtotal  += (float)($subtotal - $discount); // TODO
                $cart_discount  += (float)($discount);
                $cart_tax       += (float)($tax);
            }
            $this->subtotal = wpinv_format_amount( $cart_subtotal, NULL, true );
            $this->tax      = wpinv_format_amount( $cart_tax, NULL, true );
            $this->discount = wpinv_format_amount( $cart_discount, NULL, true );
            
            $this->recalculate_total();
            
            $this->cart_details = $cart_details;
        }

        return $this;
    }
    
    public function recalculate_totals($temp = false) {        
        $this->update_items($temp);
        $this->save( true );
        
        return $this;
    }
    
    public function needs_payment() {
        $valid_invoice_statuses = apply_filters( 'wpinv_valid_invoice_statuses_for_payment', array( 'pending' ), $this );

        if ( $this->has_status( $valid_invoice_statuses ) && $this->get_total() > 0 ) {
            $needs_payment = true;
        } else {
            $needs_payment = false;
        }

        return apply_filters( 'wpinv_needs_payment', $needs_payment, $this, $valid_invoice_statuses );
    }
    
    public function get_view_invoice_url() {
        $view_invoice_url = add_query_arg( 'invoice_key', $this->get_key(), wpinv_get_success_page_uri() );

        return apply_filters( 'wpinv_get_view_invoice_url', $view_invoice_url, $this );
    }
    
    public function get_checkout_payment_url( $on_checkout = false ) {
        $pay_url = wpinv_get_checkout_uri();

        if ( is_ssl() ) {
            $pay_url = str_replace( 'http:', 'https:', $pay_url );
        }

		if ( $on_checkout ) {
			$pay_url = add_query_arg( 'invoice_key', $this->get_key(), $pay_url );
		} else {
			$pay_url = add_query_arg( array( 'wpi_action' => 'pay_for_invoice', 'invoice_key' => $this->get_key() ), $pay_url );
		}

		return apply_filters( 'wpinv_get_checkout_payment_url', $pay_url, $this );
    }
    
    public function get_print_url() {
        $print_url = get_permalink( $this->ID );

		return apply_filters( 'wpinv_get_print_url', $print_url, $this );
    }
    
    public function generate_key( $string = '' ) {
        $auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
        return strtolower( md5( $string . date( 'Y-m-d H:i:s' ) . $auth_key . uniqid( 'wpinv', true ) ) );  // Unique key
    }
    
    public function is_recurring() {
        if ( empty( $this->cart_details ) ) {
            return false;
        }
        
        $has_subscription = false;
        foreach( $this->cart_details as $cart_item ) {
            if ( !empty( $cart_item['id'] ) && wpinv_is_recurring_item( $cart_item['id'] )  ) {
                $has_subscription = true;
                break;
            }
        }
        
        if ( count( $this->cart_details ) > 1 ) {
            $has_subscription = false;
        }

        return apply_filters( 'wpinv_invoice_has_recurring_item', $has_subscription, $this->cart_details );
    }
        
    public function get_expiration() {
        $expiration = $this->get_meta( '_wpinv_subscr_expiration', true );
        return $expiration;
    }
    
    public function get_subscription_created( $default = true ) {
        $created = $this->get_meta( '_wpinv_subscr_created', true );
        
        if ( empty( $created ) && $default ) {
            $created = $this->date;
        }
        return $created;
    }
    
    public function get_subscription_start( $formatted = true ) {
        $start   = $this->get_subscription_created();
        
        if ( $formatted ) {
            $date = date_i18n( get_option( 'date_format' ), strtotime( $start ) );
        } else {
            $date = date_i18n( 'Y-m-d H:i:s', strtotime( $start ) );
        }

        return $date;
    }
    
    public function get_subscription_end( $formatted = true ) {
        $start          = $this->get_subscription_created();
        $interval       = $this->get_subscription_interval();
        $period         = $this->get_subscription_period( true );
        $bill_times     = (int)$this->get_bill_times();
        
        if ( $bill_times == 0 ) {
            return $formatted ? __( 'Until cancelled', 'invoicing' ) : $bill_times;
        }
        
        $total_period = $start . '+' . ( $interval * $bill_times ) . ' ' . $period;
        
        if ( $formatted ) {
            $date = date_i18n( get_option( 'date_format' ), strtotime( $total_period ) );
        } else {
            $date = date_i18n( 'Y-m-d H:i:s', strtotime( $total_period ) );
        }

        return $date;
    }
    
    public function get_expiration_time() {
        return strtotime( $this->get_expiration(), current_time( 'timestamp' ) );
    }
    
    public function get_original_invoice_id() {        
        return $this->parent_invoice_id;
    }
    
    public function get_bill_times() {
        $bill_times = $this->get_meta( '_wpinv_subscr_bill_times', true );
        return $bill_times;
    }

    public function get_child_payments( $self = false ) {
        $invoices = get_posts( array(
            'post_type'         => 'wpi_invoice',
            'post_parent'       => (int)$this->ID,
            'posts_per_page'    => '999',
            'post_status'       => array( 'publish', 'complete', 'processing', 'renewal' ),
            'orderby'           => 'ID',
            'order'             => 'DESC',
            'fields'            => 'ids'
        ) );
        
        if ( $self && $this->is_complete() ) {
            if ( !empty( $invoices ) ) {
                $invoices[] = (int)$this->ID;
            } else {
                $invoices = array( $this->ID );
            }
            
            $invoices = array_unique( $invoices );
        }

        return $invoices;
    }

    public function get_total_payments() {
        return count( $this->get_child_payments() ) + 1;
    }
    
    public function get_subscriptions( $limit = -1 ) {
        $subscriptions = wpinv_get_subscriptions( array( 'parent_invoice_id' => $this->ID, 'numberposts' => $limit ) );

        return $subscriptions;
    }
    
    public function get_subscription_id() {
        $subscription_id = $this->get_meta( '_wpinv_subscr_profile_id', true );
        
        if ( empty( $subscription_id ) && !empty( $this->parent_invoice ) ) {
            $parent_invoice = wpinv_get_invoice( $this->parent_invoice );
            
            $subscription_id = $parent_invoice->get_meta( '_wpinv_subscr_profile_id', true );
        }
        
        return $subscription_id;
    }
    
    public function get_subscription_status() {
        $subscription_status = $this->get_meta( '_wpinv_subscr_status', true );
        return $subscription_status;
    }
    
    public function get_subscription_status_label() {
        switch( $this->get_subscription_status() ) {
            case 'active' :
                $status = __( 'Active', 'invoicing' );
                break;

            case 'cancelled' :
                $status = __( 'Cancelled', 'invoicing' );
                break;

            case 'expired' :
                $status = __( 'Expired', 'invoicing' );
                break;

            case 'pending' :
                $status = __( 'Pending', 'invoicing' );
                break;

            case 'failing' :
                $status = __( 'Failing', 'invoicing' );
                break;

            default:
                $status = $this->get_subscription_status();
                break;
        }

        return $status;
    }
    
    public function get_subscription_period( $full = false ) {
        $period = $this->get_meta( '_wpinv_subscr_period', true );
        
        if ( !in_array( $period, array( 'D', 'W', 'M', 'Y' ) ) ) {
            $period = 'D';
        }
        
        if ( $full ) {
            switch( $period ) {
                case 'D':
                    $period = 'day';
                break;
                case 'W':
                    $period = 'week';
                break;
                case 'M':
                    $period = 'month';
                break;
                case 'Y':
                    $period = 'year';
                break;
            }
        }
        
        return $period;
    }
    
    public function get_subscription_interval() {
        $interval = (int)$this->get_meta( '_wpinv_subscr_interval', true );
        
        if ( !$interval > 0 ) {
            $interval = 1;
        }
        
        return $interval;
    }
    
    public function failing_subscription() {
        $args = array(
            'status' => 'failing'
        );

        if ( $this->update_subscription( $args ) ) {
            do_action( 'wpinv_subscription_failing', $this->id, $this );
            return true;
        }

        return false;
    }

    public function cancel_subscription() {
        $args = array(
            'status' => 'cancelled'
        );

        if ( $this->update_subscription( $args ) ) {
            if ( is_user_logged_in() ) {
                $userdata = get_userdata( get_current_user_id() );
                $user     = $userdata->user_login;
            } else {
                $user = __( 'gateway', 'invoicing' );
            }

            $note = sprintf( __( 'Subscription #%d cancelled by %s', 'invoicing' ), $this->ID, $user );
            $this->add_note( $note );

            do_action( 'wpinv_subscription_cancelled', $this->ID, $this );
            return true;
        }

        return false;
    }

    public function can_cancel() {
        return apply_filters( 'wpinv_subscription_can_cancel', false, $this );
    }
    
    public function add_subscription( $data = array() ) {
        if ( empty( $this->ID ) ) {
            return false;
        }

        $defaults = array(
            'period'            => '',
            'initial_amount'    => '',
            'recurring_amount'  => '',
            'interval'          => 0,
            'bill_times'        => 0,
            'item_id'           => 0,
            'created'           => '',
            'expiration'        => '',
            'status'            => '',
            'profile_id'        => '',
        );

        $args = wp_parse_args( $data, $defaults );

        if ( $args['expiration'] && strtotime( 'NOW', current_time( 'timestamp' ) ) > strtotime( $args['expiration'], current_time( 'timestamp' ) ) ) {
            if ( 'active' == $args['status'] ) {
                // Force an active subscription to expired if expiration date is in the past
                $args['status'] = 'expired';
            }
        }

        do_action( 'wpinv_subscription_pre_create', $args, $data, $this );
        
        if ( !empty( $args ) ) {
            foreach ( $args as $key => $value ) {
                $this->update_meta( '_wpinv_subscr_' . $key, $value );
            }
        }

        do_action( 'wpinv_subscription_post_create', $args, $data, $this );

        return true;
    }
    
    public function update_subscription( $args = array() ) {
        if ( empty( $this->ID ) ) {
            return false;
        }

        if ( !empty( $args['expiration'] ) && $args['expiration'] && strtotime( 'NOW', current_time( 'timestamp' ) ) > strtotime( $args['expiration'], current_time( 'timestamp' ) ) ) {
            if ( !isset( $args['status'] ) || ( isset( $args['status'] ) && 'active' == $args['status'] ) ) {
                // Force an active subscription to expired if expiration date is in the past
                $args['status'] = 'expired';
            }
        }

        do_action( 'wpinv_subscription_pre_update', $args, $this );
        
        if ( !empty( $args ) ) {
            foreach ( $args as $key => $value ) {
                $this->update_meta( '_wpinv_subscr_' . $key, $value );
            }
        }

        do_action( 'wpinv_subscription_post_update', $args, $this );

        return true;
    }
    
    public function renew_subscription() {
        $expires = $this->get_expiration_time();

        // Determine what date to use as the start for the new expiration calculation
        if ( $expires > current_time( 'timestamp' ) && $this->is_subscription_active() ) {
            $base_date  = $expires;
        } else {
            $base_date  = current_time( 'timestamp' );
        }
        
        $last_day       = cal_days_in_month( CAL_GREGORIAN, date( 'n', $base_date ), date( 'Y', $base_date ) );
        $expiration     = date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . $this->get_subscription_interval() . ' ' . $this->get_subscription_period( true ), $base_date ) );

        if( date( 'j', $base_date ) == $last_day && 'D' != $this->get_subscription_period() ) {
            $expiration = date_i18n( 'Y-m-d H:i:s', strtotime( $expiration . ' +2 days' ) );
        }

        $expiration     = apply_filters( 'wpinv_subscription_renewal_expiration', $expiration, $this->ID, $this );

        do_action( 'wpinv_subscription_pre_renew', $this->ID, $expiration, $this );

        $status       = 'active';
        $times_billed = $this->get_total_payments();

        // Complete subscription if applicable
        if ( $this->get_bill_times() > 0 && $times_billed >= $this->get_bill_times() ) {
            $this->complete_subscription();
            $status = 'completed';
        }

        $args = array(
            'expiration' => $expiration,
            'status'     => $status,
        );

        if( $this->update_subscription( $args ) ) {
            $note = sprintf( __( 'Subscription #%1$s: %2$s', 'invoicing' ), wpinv_get_invoice_number( $this->ID ), $status );
            $this->add_note( $note, true );
        }

        do_action( 'wpinv_subscription_post_renew', $this->ID, $expiration, $this );
        do_action( 'wpinv_recurring_set_subscription_status', $this->ID, $status, $this );
    }
    
    public function complete_subscription() {
        $args = array(
            'status' => 'completed'
        );

        if ( $this->update_subscription( $args ) ) {
            do_action( 'wpinv_subscription_completed', $this->ID, $this );
        }
    }
    
    public function expire_subscription() {
        $args = array(
            'status' => 'expired'
        );

        if ( $this->update_subscription( $args ) ) {
            do_action( 'wpinv_subscription_expired', $this->ID, $this );
        }
    }

    public function get_cancel_url() {
        $url = wp_nonce_url( add_query_arg( array( 'wpi_action' => 'cancel_subscription', 'sub_id' => $this->ID ) ), 'wpinv-recurring-cancel' );

        return apply_filters( 'wpinv_subscription_cancel_url', $url, $this );
    }

    public function can_update() {
        return apply_filters( 'wpinv_subscription_can_update', false, $this );
    }

    public function get_update_url() {
        $url = add_query_arg( array( 'action' => 'update', 'sub_id' => $this->ID ) );

        return apply_filters( 'wpinv_subscription_update_url', $url, $this );
    }

    
    public function is_subscription_active() {
        $ret = false;

        if( ! $this->is_subscription_expired() && ( $this->get_subscription_status() == 'active' || $this->get_subscription_status() == 'cancelled' ) ) {
            $ret = true;
        }

        return apply_filters( 'wpinv_subscription_is_active', $ret, $this->ID, $this );
    }

    public function is_subscription_expired() {
        $ret = false;
        $subscription_status = $this->get_subscription_status();

        if ( $subscription_status == 'expired' ) {
            $ret = true;
        } else if ( 'active' === $subscription_status || 'cancelled' === $subscription_status ) {
            $ret        = false;
            $expiration = $this->get_expiration_time();

            if ( $expiration && strtotime( 'NOW', current_time( 'timestamp' ) ) > $expiration ) {
                $ret = true;

                if ( 'active' === $subscription_status ) {
                    $this->expire_subscription();
                }
            }
        }

        return apply_filters( 'wpinv_subscription_is_expired', $ret, $this->ID, $this );
    }
    
    public function get_new_expiration( $item_id = 0 ) {
        $item   = new WPInv_Item( $item_id );
        $interval = $item->get_recurring_interval();
        $period = $item->get_recurring_period( true );

        return date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . $interval . ' ' . $period ) );
    }
    
    public function get_subscription_data() {
        $fields = array( 'item_id', 'status', 'period', 'initial_amount', 'recurring_amount', 'interval', 'bill_times', 'expiration', 'profile_id', 'created' );
        
        $subscription_meta = array();
        foreach ( $fields as $field ) {
            if ( ( $value = $this->get_meta( '_wpinv_subscr_' . $field ) ) !== false ) {
                $subscription_meta[ $field ] = $value;
            }
        }
        
        return $subscription_meta;
    }
    
    public function is_complete() {
        if ( $this->has_status( array( 'publish', 'complete', 'processing', 'renewal' ) ) ) {
            return true;
        }
        
        return false;
    }
}
