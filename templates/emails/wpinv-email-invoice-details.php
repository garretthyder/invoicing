<?php
// don't load directly
if ( !defined('ABSPATH') )
    die('-1');

$sent_to_admin = !empty( $sent_to_admin ) ? true : false;

do_action( 'wpinv_email_before_invoice_details', $invoice, $sent_to_admin ); ?>
<div id="wpinv-email-details">
    <h3 class="wpinv-details-t"><?php echo apply_filters( 'wpinv_email_details_title', __( 'Invoice Details', 'invoicing' ) ); ?></h3>
    <table class="table table-bordered table-sm">
        <?php if ( $invoice_number = $invoice->get_number() ) { ?>
            <tr>
                <td><?php _e( 'Invoice Number', 'invoicing' ); ?></td>
                <td><?php if ( $sent_to_admin ) { ?><a href="<?php echo esc_url( get_edit_post_link( $invoice->ID ) ) ;?>"><?php echo esc_html( $invoice_number ); ?></a><?php } else { echo esc_html( $invoice_number ); } ?></td>
            </tr>
        <?php } ?>
        <tr>
            <td><?php _e( 'Invoice Status', 'invoicing' ); ?></td>
            <td><?php echo $invoice->get_status( true ); ?></td>
        </tr>
        <tr>
            <td><?php _e( 'Payment Method', 'invoicing' ); ?></td>
            <td><?php echo $invoice->get_gateway_title(); ?></td>
        </tr>
        <?php if ( $invoice_date = $invoice->get_invoice_date( false ) ) { ?>
            <tr>
                <td><?php _e( 'Invoice Date', 'invoicing' ); ?></td>
                <td><?php echo wp_sprintf( '<time datetime="%s">%s</time>', date_i18n( 'c', strtotime( $invoice_date ) ), $invoice->get_invoice_date() ); ?></td>
            </tr>
        <?php } ?>
        <?php if ( empty( $sent_to_admin ) && $owner_vat_number = wpinv_owner_vat_number() ) { ?>
            <tr>
                <td><?php _e( 'Owner VAT Number', 'invoicing' ); ?></td>
                <td><?php echo $owner_vat_number; ?></td>
            </tr>
        <?php } ?>
        <?php if ( $user_vat_number = $invoice->vat_number ) { ?>
            <tr>
                <td><?php _e( 'Invoice VAT Number', 'invoicing' ); ?></td>
                <td><?php echo $user_vat_number; ?></td>
            </tr>
        <?php } ?>
        <tr class="table-active">
            <td><strong><?php _e( 'Total Amount', 'invoicing' ) ?></strong></td>
            <td><strong><?php echo $invoice->get_total( true ); ?></strong></td>
        </tr>
    </table>
</div>
<?php do_action( 'wpinv_email_after_invoice_details', $invoice, $sent_to_admin ); ?>