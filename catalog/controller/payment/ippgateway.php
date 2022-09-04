<?php
namespace Opencart\Catalog\Controller\Extension\IPPGateway\Payment;
class IPPGateway extends \Opencart\System\Engine\Controller {
    private $error = [];

    public function __construct($registry) {
        parent::__construct($registry);

        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set('precision', 14);
            ini_set('serialize_precision', 14);
        }
    }

    public function index(): string {
        if ($this->config->get('payment_ippgateway_client_id') && $this->config->get('payment_ippgateway_secret') && !$this->webhook()) {
            $this->load->language('extension/ippgateway/payment/ippgateway');

            $this->load->model('extension/ippgateway/payment/ippgateway');
            $this->load->model('localisation/country');
            $this->load->model('checkout/order');

            $country = $this->model_localisation_country->getCountry($this->config->get('config_country_id'));

            // Setting
            $_config = new \Opencart\System\Engine\Config();
            $_config->addPath(DIR_EXTENSION . 'ippgateway/system/config/');
            $_config->load('ippgateway');

            $config_setting = $_config->get('ippgateway_setting');

            $setting = array_replace_recursive((array)$config_setting, (array)$this->config->get('payment_ippgateway_setting'));

            $data['environment'] = $this->config->get('payment_ippgateway_environment');
            $data['client_id'] = $this->config->get('payment_ippgateway_client_id');
            $data['secret'] = $this->config->get('payment_ippgateway_secret');
            $data['transaction_method'] = $this->config->get('payment_ippgateway_transaction_method');
            $data['locale'] = preg_replace('/-(.+?)+/', '', $this->config->get('config_language')) . '_' . $country['iso_code_2'];

            $data['currency_code'] = $this->session->data['currency'];
            $data['currency_value'] = $this->currency->getValue($this->session->data['currency']);

            if (empty($setting['currency'][$data['currency_code']]['express_status'])) {
                $data['currency_code'] = $this->config->get('payment_ippgateway_currency_code');
                $data['currency_value'] = $this->config->get('payment_ippgateway_currency_value');
            }

            $data['decimal_place'] = $setting['currency'][$data['currency_code']]['decimal_place'];

            $data['language'] = $this->config->get('config_language');

            $data['express_status'] = $setting['checkout']['express']['status'];

            $data['button_align'] = $setting['checkout']['express']['button_align'];
            $data['button_size'] = $setting['checkout']['express']['button_size'];
            $data['button_color'] = $setting['checkout']['express']['button_color'];
            $data['button_shape'] = $setting['checkout']['express']['button_shape'];
            $data['button_label'] = $setting['checkout']['express']['button_label'];
            $data['button_width'] = $setting['button_width'][$data['button_size']];

            $data['button_enable_funding'] = [];
            $data['button_disable_funding'] = [];

            foreach ($setting['button_funding'] as $button_funding) {
                if ($setting['checkout']['express']['button_funding'][$button_funding['code']] == 1) {
                    $data['button_enable_funding'][] = $button_funding['code'];
                }

                if ($setting['checkout']['express']['button_funding'][$button_funding['code']] == 2) {
                    $data['button_disable_funding'][] = $button_funding['code'];
                }
            }

            $data['card_status'] = $setting['checkout']['card']['status'];

            $data['form_align'] = $setting['checkout']['card']['form_align'];
            $data['form_size'] = $setting['checkout']['card']['form_size'];
            $data['form_width'] = $setting['form_width'][$data['form_size']];
            $data['secure_status'] = $setting['checkout']['card']['secure_status'];

            $data['message_status'] = $setting['checkout']['message']['status'];
            $data['message_align'] = $setting['checkout']['message']['message_align'];
            $data['message_size'] = $setting['checkout']['message']['message_size'];
            $data['message_width'] = $setting['message_width'][$data['message_size']];
            $data['message_layout'] = $setting['checkout']['message']['message_layout'];
            $data['message_text_color'] = $setting['checkout']['message']['message_text_color'];
            $data['message_text_size'] = $setting['checkout']['message']['message_text_size'];
            $data['message_flex_color'] = $setting['checkout']['message']['message_flex_color'];
            $data['message_flex_ratio'] = $setting['checkout']['message']['message_flex_ratio'];
            $data['message_placement'] = 'payment';

            $data['order_id'] = $this->session->data['order_id'];

            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            $data['amount'] = number_format($order_info['total'] * $data['currency_value'], $data['decimal_place'], '.', '');
            $data['currency'] = $this->session->data['currency'];
            ;
            $data['transaction_type'] = "ECOM";

            require_once DIR_EXTENSION . 'ippgateway/system/library/ippgateway.php';

            $ippgateway_info = [
                'client_id' => $data['client_id'],
                'secret' => $data['secret'],
                'environment' => $data['environment']
            ];

            $ippgateway = new \Opencart\System\Library\IPPGateway($ippgateway_info);

            $checkout_id = $ippgateway->checkout_id($data);

            $data['checkout_id'] = $checkout_id["content"]["checkout_id"];
            $data['cryptogram'] = $checkout_id["content"]["cryptogram"];

            if ($ippgateway->hasErrors()) {
                $error_messages = [];

                $errors = $ippgateway->getErrors();

                foreach ($errors as $error) {
                    if (isset($error['name']) && ($error['name'] == 'CURLE_OPERATION_TIMEOUTED')) {
                        $error['message'] = $this->language->get('error_timeout');
                    }

                    if (isset($error['details'][0]['description'])) {
                        $error_messages[] = $error['details'][0]['description'];
                    } else {
                        $error_messages[] = $error['message'];
                    }

                    $this->model_extension_ippgateway_payment_ippgateway->log($error, $error['message']);
                }

                $this->error['warning'] = implode(' ', $error_messages);
            }

            if ($this->error && isset($this->error['warning'])) {
                $this->error['warning'] .= ' ' . sprintf($this->language->get('error_payment'), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
            }


            return $this->load->view('extension/ippgateway/payment/ippgateway', $data);
        }

        return '';
    }

    public function createOrder(): void {
        $this->load->language('extension/ippgateway/payment/ippgateway');

        $this->load->model('extension/ippgateway/payment/ippgateway');
        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        // Setting
        $_config = new \Opencart\System\Engine\Config();
        $_config->addPath(DIR_EXTENSION . 'ippgateway/system/config/');
        $_config->load('ippgateway');

        $config_setting = $_config->get('ippgateway_setting');

        $setting = array_replace_recursive((array)$config_setting, (array)$this->config->get('payment_ippgateway_setting'));

        $transaction_method = $this->config->get('payment_ippgateway_transaction_method');

        $currency_code = $this->config->get('payment_ippgateway_card_currency_code');
        $currency_value = $this->config->get('payment_ippgateway_card_currency_value');

        $decimal_place = $setting['currency'][$currency_code]['decimal_place'];

        if ($this->cart->hasShipping()) {
            $shipping_info['name']['full_name'] = $order_info['shipping_firstname'];
            $shipping_info['name']['full_name'] .= ($order_info['shipping_lastname'] ? (' ' . $order_info['shipping_lastname']) : '');
            $shipping_info['address']['address_line_1'] = $order_info['shipping_address_1'];
            $shipping_info['address']['address_line_2'] = $order_info['shipping_address_2'];
            $shipping_info['address']['admin_area_1'] = $order_info['shipping_zone'];
            $shipping_info['address']['admin_area_2'] = $order_info['shipping_city'];
            $shipping_info['address']['postal_code'] = $order_info['shipping_postcode'];

            if ($order_info['shipping_country_id']) {
                $this->load->model('localisation/country');

                $country_info = $this->model_localisation_country->getCountry($order_info['shipping_country_id']);

                if ($country_info) {
                    $shipping_info['address']['country_code'] = $country_info['iso_code_2'];
                }
            }

            $shipping_preference = 'SET_PROVIDED_ADDRESS';
        } else {
            $shipping_preference = 'NO_SHIPPING';
        }

        $item_info = [];

        $item_total = 0;
        $tax_total = 0;

        foreach ($this->cart->getProducts() as $product) {
            $product_price = number_format($product['price'] * $currency_value, $decimal_place, '.', '');

            $item_info[] = [
                'name' => $product['name'],
                'sku' => $product['model'],
                'url' => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product['product_id']),
                'quantity' => $product['quantity'],
                'unit_amount' => [
                    'currency_code' => $currency_code,
                    'value' => $product_price
                ]
            ];

            $item_total += $product_price * $product['quantity'];

            if ($product['tax_class_id']) {
                $tax_rates = $this->tax->getRates($product['price'], $product['tax_class_id']);

                foreach ($tax_rates as $tax_rate) {
                    $tax_total += ($tax_rate['amount'] * $product['quantity']);
                }
            }
        }

        $item_total = number_format($item_total, $decimal_place, '.', '');
        $tax_total = number_format($tax_total * $currency_value, $decimal_place, '.', '');

        $discount_total = 0;
        $handling_total = 0;
        $shipping_total = 0;

        if (isset($this->session->data['shipping_method'])) {
            $shipping = explode('.', $this->session->data['shipping_method']);

            if (isset($shipping[0]) && isset($shipping[1]) && isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])) {
                $shipping_method_info = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];

                $shipping_total = $this->tax->calculate($shipping_method_info['cost'], $shipping_method_info['tax_class_id'], true);
                $shipping_total = number_format($shipping_total * $currency_value, $decimal_place, '.', '');
            }
        }

        $order_total = number_format($order_info['total'] * $currency_value, $decimal_place, '.', '');

        $rebate = number_format($item_total + $tax_total + $shipping_total - $order_total, $decimal_place, '.', '');

        if ($rebate > 0) {
            $discount_total = $rebate;
        } elseif ($rebate < 0) {
            $handling_total = -$rebate;
        }

        $amount_info = [
            'currency_code' => $currency_code,
            'value' => $order_total,
            'breakdown' => [
                'item_total' => [
                    'currency_code' => $currency_code,
                    'value' => $item_total
                ],
                'tax_total' => [
                    'currency_code' => $currency_code,
                    'value' => $tax_total
                ],
                'shipping' => [
                    'currency_code' => $currency_code,
                    'value' => $shipping_total
                ],
                'handling' => [
                    'currency_code' => $currency_code,
                    'value' => $handling_total
                ],
                'discount' => [
                    'currency_code' => $currency_code,
                    'value' => $discount_total
                ]
            ]
        ];



        if ($this->cart->hasShipping()) {
            $ippgateway_order_info = [
                'intent' => strtoupper($transaction_method),
                'purchase_units' => [
                    [
                        'reference_id' => 'default',
                        'description' => 'Your order ' . $order_info['order_id'],
                        'invoice_id' => $order_info['order_id'],
                        'shipping' => $shipping_info,
                        'items' => $item_info,
                        'amount' => $amount_info
                    ]
                ],
                'application_context' => [
                    'shipping_preference' => $shipping_preference
                ]
            ];
        } else {
            $ippgateway_order_info = [
                'intent' => strtoupper($transaction_method),
                'purchase_units' => [
                    [
                        'reference_id' => 'default',
                        'description' => 'Your order ' . $order_info['order_id'],
                        'invoice_id' => $order_info['order_id'],
                        'items' => $item_info,
                        'amount' => $amount_info
                    ]
                ],
                'application_context' => [
                    'shipping_preference' => $shipping_preference
                ]
            ];
        }

        $data['ippgateway_order_id'] = '';
        if (isset($result['id']) && isset($result['status']) && !$this->error) {
            $this->model_extension_ippgateway_payment_ippgateway->log($result, 'Create Order');

            if ($result['status'] == 'VOIDED') {
                $this->error['warning'] = sprintf($this->language->get('error_order_voided'), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
            }

            if ($result['status'] == 'COMPLETED') {
                $this->error['warning'] = sprintf($this->language->get('error_order_completed'), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
            }

            if (!$this->error) {
                $data['ippgateway_order_id'] = $result['id'];
            }
        }

        $data['language'] = $this->config->get('config_language');

        $data['error'] = $this->error;

        $message = sprintf($this->language->get('text_order_message'), true);
        $order_status_id = 0;

        $order_data = [];
        $order_data['total'] = $order_info['total'];


        $order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
        $order_data['store_id'] = $this->config->get('config_store_id');
        $order_data['store_name'] = $this->config->get('config_name');
        $order_data['store_url'] = $this->config->get('config_url');

        $order_data['customer_id'] = $this->session->data['customer']['customer_id'];
        $order_data['customer_group_id'] = $this->session->data['customer']['customer_group_id'];
        $order_data['firstname'] = $this->session->data['customer']['firstname'];
        $order_data['lastname'] = $this->session->data['customer']['lastname'];
        $order_data['email'] = $this->session->data['customer']['email'];
        $order_data['telephone'] = $this->session->data['customer']['telephone'];
        $order_data['custom_field'] = $this->session->data['customer']['custom_field'];

        $order_data['payment_firstname'] = $this->session->data['shipping_address']['firstname'] ?: "";
        $order_data['payment_lastname'] = $this->session->data['shipping_address']['lastname']?: "";
        $order_data['payment_company'] = $this->session->data['shipping_address']['company']?: "";
        $order_data['payment_address_1'] = $this->session->data['shipping_address']['address_1']?: "";
        $order_data['payment_address_2'] = $this->session->data['shipping_address']['address_2']?: "";
        $order_data['payment_city'] = $this->session->data['shipping_address']['city'];
        $order_data['payment_postcode'] = $this->session->data['shipping_address']['postcode'];
        $order_data['payment_zone'] = $this->session->data['shipping_address']['zone'];
        $order_data['payment_zone_id'] = $this->session->data['shipping_address']['zone_id'];
        $order_data['payment_country'] = $this->session->data['shipping_address']['country'];
        $order_data['payment_country_id'] = $this->session->data['shipping_address']['country_id'];
        $order_data['payment_address_format'] = $this->session->data['shipping_address']['address_format'];
        $order_data['payment_custom_field'] = (isset($this->session->data['shipping_address']['custom_field']) ? $this->session->data['shipping_address']['custom_field'] : []);

        if (isset($this->session->data['payment_methods'][$this->session->data['payment_method']])) {
            $payment_method_info = $this->session->data['payment_methods'][$this->session->data['payment_method']];
        }

        if (isset($payment_method_info['title'])) {
            $order_data['payment_method'] = $payment_method_info['title'];
        } else {
            $order_data['payment_method'] = '';
        }

        if (isset($payment_method_info['code'])) {
            $order_data['payment_code'] = $payment_method_info['code'];
        } else {
            $order_data['payment_code'] = '';
        }

        if ($this->cart->hasShipping()) {
            $order_data['shipping_firstname'] = $this->session->data['shipping_address']['firstname'];
            $order_data['shipping_lastname'] = $this->session->data['shipping_address']['lastname'];
            $order_data['shipping_company'] = $this->session->data['shipping_address']['company'];
            $order_data['shipping_address_1'] = $this->session->data['shipping_address']['address_1'];
            $order_data['shipping_address_2'] = $this->session->data['shipping_address']['address_2'];
            $order_data['shipping_city'] = $this->session->data['shipping_address']['city'];
            $order_data['shipping_postcode'] = $this->session->data['shipping_address']['postcode'];
            $order_data['shipping_zone'] = $this->session->data['shipping_address']['zone'];
            $order_data['shipping_zone_id'] = $this->session->data['shipping_address']['zone_id'];
            $order_data['shipping_country'] = $this->session->data['shipping_address']['country'];
            $order_data['shipping_country_id'] = $this->session->data['shipping_address']['country_id'];
            $order_data['shipping_address_format'] = $this->session->data['shipping_address']['address_format'];
            $order_data['shipping_custom_field'] = (isset($this->session->data['shipping_address']['custom_field']) ? $this->session->data['shipping_address']['custom_field'] : []);

            if (isset($this->session->data['shipping_method'])) {
                $shipping = explode('.', $this->session->data['shipping_method']);

                if (isset($shipping[0]) && isset($shipping[1]) && isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])) {
                    $shipping_method_info = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];
                }
            }

            if (isset($shipping_method_info['title'])) {
                $order_data['shipping_method'] = $shipping_method_info['title'];
            } else {
                $order_data['shipping_method'] = '';
            }

            if (isset($shipping_method_info['code'])) {
                $order_data['shipping_code'] = $shipping_method_info['code'];
            } else {
                $order_data['shipping_code'] = '';
            }
        } else {
            $order_data['shipping_firstname'] = '';
            $order_data['shipping_lastname'] = '';
            $order_data['shipping_company'] = '';
            $order_data['shipping_address_1'] = '';
            $order_data['shipping_address_2'] = '';
            $order_data['shipping_city'] = '';
            $order_data['shipping_postcode'] = '';
            $order_data['shipping_zone'] = '';
            $order_data['shipping_zone_id'] = '';
            $order_data['shipping_country'] = '';
            $order_data['shipping_country_id'] = '';
            $order_data['shipping_address_format'] = '';
            $order_data['shipping_custom_field'] = [];
            $order_data['shipping_method'] = '';
            $order_data['shipping_code'] = '';
        }

        $order_data['products'] = [];

        foreach ($this->cart->getProducts() as $product) {
            $option_data = [];

            foreach ($product['option'] as $option) {
                $option_data[] = [
                    'product_option_id'       => $option['product_option_id'],
                    'product_option_value_id' => $option['product_option_value_id'],
                    'option_id'               => $option['option_id'],
                    'option_value_id'         => $option['option_value_id'],
                    'name'                    => $option['name'],
                    'value'                   => $option['value'],
                    'type'                    => $option['type']
                ];
            }

            $order_data['products'][] = [
                'product_id' 	=> $product['product_id'],
                'master_id'  	=> $product['master_id'],
                'name'       	=> $product['name'],
                'model'      	=> $product['model'],
                'option'     	=> $option_data,
                'subscription' 	=> $product['subscription'],
                'download'   	=> $product['download'],
                'quantity'   	=> $product['quantity'],
                'subtract'   	=> $product['subtract'],
                'price'      	=> $product['price'],
                'total'      	=> $product['total'],
                'tax'       	=> $this->tax->getTax($product['price'], $product['tax_class_id']),
                'reward'     	=> $product['reward']
            ];
        }
        $order_data['vouchers'] = [];
        if (!empty($this->session->data['vouchers'])) {
            foreach ($this->session->data['vouchers'] as $voucher) {
                $order_data['vouchers'][] = [
                    'description'      => $voucher['description'],
                    'code'             => token(10),
                    'to_name'          => $voucher['to_name'],
                    'to_email'         => $voucher['to_email'],
                    'from_name'        => $voucher['from_name'],
                    'from_email'       => $voucher['from_email'],
                    'voucher_theme_id' => $voucher['voucher_theme_id'],
                    'message'          => $voucher['message'],
                    'amount'           => $voucher['amount']
                ];
            }
        }

        $order_data['comment'] = (isset($this->session->data['comment']) ? $this->session->data['comment'] : '');
        $order_data['affiliate_id'] = 0;
        $order_data['commission'] = 0;
        $order_data['marketing_id'] = 0;
        $order_data['tracking'] = '';

        if ($this->config->get('config_affiliate_status') && isset($this->session->data['tracking'])) {
            $subtotal = $this->cart->getSubTotal();

            // Affiliate
            $this->load->model('account/affiliate');

            $affiliate_info = $this->model_account_affiliate->getAffiliateByTracking($this->session->data['tracking']);

            if ($affiliate_info) {
                $order_data['affiliate_id'] = $affiliate_info['customer_id'];
                $order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
                $order_data['tracking'] = $this->session->data['tracking'];
            }
        }

        $order_data['language_id'] = $this->config->get('config_language_id');
        $order_data['language_code'] = $this->config->get('config_language');

        $order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
        $order_data['currency_code'] = $this->session->data['currency'];
        $order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);
        $order_data['ip'] = $this->request->server['REMOTE_ADDR'];

        if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
            $order_data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
            $order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
        } else {
            $order_data['forwarded_ip'] = '';
        }

        if (isset($this->request->server['HTTP_USER_AGENT'])) {
            $order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
        } else {
            $order_data['user_agent'] = '';
        }

        if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
            $order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];
        } else {
            $order_data['accept_language'] = '';
        }

        $this->session->data['order_id'] = $this->model_checkout_order->addOrder($order_data);
        $message = sprintf($this->language->get('text_order_message'), true);

        $this->model_checkout_order->addHistory($this->session->data['order_id'], $order_status_id, $message);
        $setting = array_replace_recursive((array)$config_setting, (array)$this->config->get('payment_ippgateway_setting'));
        $order_status_id = $setting['order_status']['pending']['id'];
        $this->model_checkout_order->addHistory($this->session->data['order_id'], $order_status_id, sprintf("Transaction ID: " . $this->request->get["transaction_id"]));
        $this->model_checkout_order->addHistory($this->session->data['order_id'], $order_status_id, sprintf("Transaction Key: " . $this->request->get["transaction_key"]));
        $this->model_checkout_order->addHistory($this->session->data['order_id'], $order_status_id);

        $this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language')));
    }

}