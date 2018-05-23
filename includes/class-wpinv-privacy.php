<?php
/**
 * Privacy/GDPR related functionality which ties into WordPress functionality.
 */

defined( 'ABSPATH' ) || exit;

/**
 * WPInv_Privacy Class.
 */
class WPInv_Privacy extends WPInv_Abstract_Privacy {

    /**
     * Init - hook into events.
     */
    public function __construct() {
        parent::__construct( __( 'Invoicing', 'invoicing' ) );

        // Include supporting classes.
        include_once 'class-wpinv-privacy-exporters.php';

        // This hook registers Invoicing data exporters.
        $this->add_exporter( 'wpinv-customer-invoices', __( 'Customer Invoices', 'invoicing' ), array( 'WPInv_Privacy_Exporters', 'customer_invoice_data_exporter' ) );
    }

    /**
     * Add privacy policy content for the privacy policy page.
     *
     * @since 3.4.0
     */
    public function get_privacy_message() {

        $content = '<h2>' . __( 'Invoices and checkout', 'geodirectory' ) . '</h2>' .
                   '<div contenteditable="false">' .
                   '<p class="wp-policy-help">' . __( 'Example privacy texts.', 'geodirectory' ) . '</p>' .
                   '</div>' .
                   '<p>' . __( 'We collect information about you during the checkout process on our site. This information may include, but is not limited to, your name, email address, phone number, address, IP and any other details that might be requested from you for the purpose of processing your payment and retaining your invoice details for legal reasons.', 'geodirectory' ) . '</p>' .
                   '<p>' . __( 'Handling this data also allows us to:', 'geodirectory' ) . '</p>' .
                   '<ul>' .
                   '<li>' . __( '- Send you important account/order/service information.', 'geodirectory' ) . '</li>' .
                   '<li>' . __( '- Estimate taxes based on your location.', 'geodirectory' ) . '</li>' .
                   '<li>' . __( '- Respond to your queries or complaints.', 'geodirectory' ) . '</li>' .
                   '<li>' . __( '- Process payments and to prevent fraudulent transactions. We do this on the basis of our legitimate business interests.', 'geodirectory' ) . '</li>' .
                   '<li>' . __( '- Retain historical payment and invoice history. We do this on the basis of legal obligations.', 'geodirectory' ) . '</li>' .
                   '<li>' . __( '- Set up and administer your account, provide technical and/or customer support, and to verify your identity. We do this on the basis of our legitimate business interests.', 'geodirectory' ) . '</li>' .
                   '</ul>' .
                   '<p>' . __( 'In addition to collecting information at checkout we may also use and store your contact details when manually creating invoices for require payments relating to prior contractual agreements or agreed terms.', 'geodirectory' ) . '</p>' .
                   '<h2>' . __( 'What we share with others', 'geodirectory' ) . '</h2>' .
                   '<p>' . __( 'We share information with third parties who help us provide our payment and invoicing services to you; for example --', 'geodirectory' ) . '</p>' .
                   '<div contenteditable="false">' .
                   '<p class="wp-policy-help">' . __( 'In this subsection you should list which third party payment processors you’re using to take payments since these may handle customer data. We’ve included PayPal as an example, but you should remove this if you’re not using PayPal.', 'geodirectory' ) . '</p>' .
                   '</div>' .
                   '<p>' . __( 'We accept payments through PayPal. When processing payments, some of your data will be passed to PayPal, including information required to process or support the payment, such as the purchase total and billing information.', 'geodirectory' ) . '</p>' .
                   '<p>' . __( 'Please see the <a href="https://www.paypal.com/us/webapps/mpp/ua/privacy-full">PayPal Privacy Policy</a> for more details.', 'geodirectory' ) . '</p>';



//        $content = '
//			<div contenteditable="false">' .
//            '<p class="wp-policy-help">' .
//            __( 'Invoicing uses the following privacy.', 'invoicing' ) .
//            '</p>' .
//            '</div>';

        return apply_filters( 'wpinv_privacy_policy_content', $content );
    }

}

new WPInv_Privacy();