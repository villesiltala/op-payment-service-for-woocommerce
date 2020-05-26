<?php
/**
 * OP Payment Service WooCommerce payment Card controller class
 */

namespace OpMerchantServices\WooCommercePaymentGateway\Controllers;

use OpMerchantServices\SDK\Exception\HmacException;
use OpMerchantServices\SDK\Exception\ValidationException;
use OpMerchantServices\WooCommercePaymentGateway\Gateway;
use OpMerchantServices\WooCommercePaymentGateway\Plugin;
use WC_Payment_Tokens;
use WP_Error;
use WP_HTTP_Response;
use Exception;

class Card extends AbstractController
{
    protected function add()
    {
        $gateway = new Gateway();
        try {
            $gateway->add_card_form();
        } catch (HmacException $e) {
        } catch (ValidationException $e) {
        }
    }

    /**
     * @return WP_Error|WP_HTTP_Response
     * @throws Exception
     */
    protected function delete()
    {
        $this->validate_request();

        $body = file_get_contents("php://input");
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new Exception('Failed to decode JSON object');
        }

        /** @var \WP_User $current_user */
        $current_user = wp_get_current_user();

        $token_id = $data['token_id'];

        if (!$current_user->ID || !$token_id) {
            return new WP_Error('cant-delete', __('message', 'text-domain'), array('status' => 500));
        }

        $customer_tokens = WC_Payment_Tokens::get_customer_tokens($current_user->ID, Plugin::GATEWAY_ID);
        $customer_token_ids = array_keys($customer_tokens);

        if (!in_array($token_id, $customer_token_ids)) {
            return new WP_Error('cant-delete', __('message', 'text-domain'), array('status' => 500));
        }

        try {
            WC_Payment_Tokens::delete($token_id);
            wc_add_notice(__('Card was deleted successfully', 'op-payment-service-woocommerce'), 'success');
            wp_send_json_success($data);
            return new WP_HTTP_Response(['type' => 'success'], 200);
        } catch (\Exception $e) {
            wc_add_notice(__('Card could not be deleted', 'op-payment-service-woocommerce'), 'error');
            wp_send_json_error();
            return new WP_Error('cant-delete', __('message', 'text-domain'), array('status' => 500));
        }
    }

    private function validate_request()
    {
        if (strtoupper($_SERVER['REQUEST_METHOD']) != 'POST') {
            throw new Exception('Only POST requests are allowed');
        }
        $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        if (stripos($content_type, 'application/json') === false) {
            throw new Exception('Content-Type must be application/json');
        }
    }
}

