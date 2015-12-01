<?php

class ControllerPaymentPayfortStart extends Controller {

    public function index() {
        $this->language->load('payment/payfort_start');
        $this->data['button_confirm'] = $this->language->get('button_confirm');
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/payfort_start.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/payment/payfort_start.tpl';
        } else {
            $this->template = 'default/template/payment/payfort_start.tpl';
        }
        $this->render();
    }

    public function send() {
        require_once './vendor/autoload.php';
        if ($this->config->get('payfort_start_transaction')) {
            $capture = FALSE;
        } else {
            $capture = TRUE;
        }
        if ($this->config->get('payfort_start_test')) {
            $payfort_start_secret_api = $this->config->get('payfort_start_entry_test_secret_key');
        } else {
            $payfort_start_secret_api = $this->config->get('payfort_start_entry_live_secret_key');
        }
        $token = $_POST['payment_token'];
        $email = $_POST['payment_email'];
        $this->load->model('checkout/order');
        $order_id = $this->session->data['order_id'];
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $order_description = "Charge for order";
        $amount = $order['total'];
        if (file_exists(DIR_SYSTEM . '../data/currencies.json')) {
            $currency_json_data = json_decode(file_get_contents(HTTP_SERVER . 'data/currencies.json'), 1);
            $currency_multiplier = $currency_json_data[$order['currency_code']];
        } else {
            $currency_multiplier= 100;
        }
        $amount_in_cents = $amount * $currency_multiplier;
        $version = "0.2";
        $billing_address = array(
            "first_name" => $order['payment_firstname'],
            "last_name" => $order['payment_lastname'],
            "country" => $order['payment_country'],
            "city" => $order['payment_city'],
            "address_1" => $order['payment_address_1'],
            "address_2" => $order['payment_address_2'],
            "phone" => $order['telephone'],
            "postcode" => $order['payment_postcode']
        );
	if ($this->cart->hasShipping()) {
	    $shipping_address = array(
	        "first_name" => $order['shipping_firstname'],
	        "last_name" => $order['shipping_lastname'],
	        "country" => $order['shipping_country'],
	        "city" => $order['shipping_city'],
	        "address_1" => $order['shipping_address_1'],
	        "address_2" => $order['shipping_address_2'],
	        "phone" => $order['telephone'],
	        "postcode" => $order['shipping_postcode']
	    );
	}else{
	    $shipping_address = $billing_address;
	}
        if ($order['customer_id'] != 0) {
            $this->load->model('account/customer');
            $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
        }

        $user_name = ($order['customer_id'] == 0) ? "guest" : $customer_info['firstname'];

        $registered_at = ($order['customer_id'] == 0) ? date(DATE_ISO8601, strtotime(date("Y-m-d H:i:s"))) : date(DATE_ISO8601, strtotime($customer_info['date_added']));

        $products = $this->cart->getProducts();
        $order_items_array_full = array();
        foreach ($products as $key => $items) {
            $order_items_array['title'] = $items['name'];
            $order_items_array['amount'] = $items['price'];
            $order_items_array['quantity'] = $items['quantity'];
            array_push($order_items_array_full, $order_items_array);
        }

        $shopping_cart_array = array(
            'user_name' => $user_name,
            'registered_at' => $registered_at,
            'items' => $order_items_array_full,
            'billing_address' => $billing_address,
            'shipping_address' => $shipping_address
        );

        $userAgent = 'Opencart ' . VERSION . ' / Start Plugin ' . $version;

        Start::setUserAgent($userAgent);
        Start::setApiKey($payfort_start_secret_api);
        $json = array();
        try {
             $charge_args = array(
                'description' => $order_description . ': ' . $order_id, // only 255 chars
                'card' => $token,
                'currency' => $order['currency_code'],
                'email' => $email,
                'ip' => $_SERVER["REMOTE_ADDR"],
                'amount' => $amount_in_cents,
                'capture' => $capture,
                'shopping_cart' => $shopping_cart_array,
                'metadata' => array('reference_id' => $order_id)
            );
            $charge = Start_Charge::create($charge_args);
            $this->model_checkout_order->confirm($order_id, $this->config->get('config_order_status_id'));
            $this->model_checkout_order->update($order_id, $this->config->get('payfort_start_order_status_id'), 'Charge added: ' . $order_id, false);
            $json['success'] = $this->url->link('checkout/success');
        } catch (Start_Error_Banking $e) {
            if ($e->getErrorCode() == "card_declined") {
                $json['error'] = "Card declined. Please use another card";
            } else {
                $json['error'] = $e->getMessage();
            }
        }
        $this->response->setOutput(json_encode($json));
    }
}


