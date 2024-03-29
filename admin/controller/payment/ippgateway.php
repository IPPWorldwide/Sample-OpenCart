<?php
namespace Opencart\Admin\Controller\Extension\IPPGateway\Payment;
class IPPGateway extends \Opencart\System\Engine\Controller {
	private $error = [];
	
	public function index(): void {
		$this->load->language('extension/ippgateway/payment/ippgateway');
		
		$this->load->model('extension/ippgateway/payment/ippgateway');
		
		$this->document->setTitle($this->language->get('heading_title'));
			
		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extensions'),
			'href' => $this->url->link('marketplace/opencart/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/ippgateway/payment/ippgateway', 'user_token=' . $this->session->data['user_token'])
		];
		
		$data['save'] = $this->url->link('extension/ippgateway/payment/ippgateway|save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');
						
		$data['server'] = HTTP_SERVER;
		$data['catalog'] = HTTP_CATALOG;
						
		// Setting 		
		$_config = new \Opencart\System\Engine\Config();
		$_config->addPath(DIR_EXTENSION . 'ippgateway/system/config/');
		$_config->load('ippgateway');
		
		$data['setting'] = $_config->get('ippgateway_setting');
		
		if (isset($this->session->data['environment']) && isset($this->session->data['authorization_code']) && isset($this->session->data['shared_id']) && isset($this->session->data['seller_nonce']) && isset($this->request->get['merchantIdInIPPGateway'])) {
			$environment = $this->session->data['environment'];
            $client_id = $this->config->get('payment_ippgateway_client_id');
            $secret = $this->config->get('payment_ippgateway_secret_id');

			require_once DIR_EXTENSION . 'ippgateway/system/library/ippgateway.php';

			$setting = $this->model_setting_setting->getSetting('payment_ippgateway');
						
			$setting['payment_ippgateway_environment'] = $environment;
			$setting['payment_ippgateway_client_id'] = $client_id;
			$setting['payment_ippgateway_secret'] = $secret;

			$this->model_setting_setting->editSetting('payment_ippgateway', $setting);
						
			unset($this->session->data['authorization_code']);
			unset($this->session->data['shared_id']);
			unset($this->session->data['seller_nonce']);
		}
		
		if (isset($environment)) {
			$data['environment'] = $environment;
		} elseif (isset($this->request->post['payment_ippgateway_environment'])) {
			$data['environment'] = $this->request->post['payment_ippgateway_environment'];
		} elseif ($this->config->get('payment_ippgateway_environment')) {
			$data['environment'] = $this->config->get('payment_ippgateway_environment');
		} else {
			$data['environment'] = 'production';
		}
				
		$data['seller_nonce'] = $this->token(50);

        if (isset($this->request->post['payment_ippgateway_client_id'])) {
            $data['client_id'] = $this->request->post['payment_ippgateway_client_id'];
        } elseif ($this->config->get('payment_ippgateway_client_id')) {
            $data['client_id'] = $this->config->get('payment_ippgateway_client_id');
        }

        if (isset($this->request->post['payment_ippgateway_secret'])) {
            $data['secret'] = $this->request->post['payment_ippgateway_secret'];
        } elseif ($this->config->get('payment_ippgateway_secret')) {
            $data['secret'] = $this->config->get('payment_ippgateway_secret');
        }

		if (isset($webhook_id)) {
			$data['webhook_id'] = $webhook_id;
		} elseif (isset($this->request->post['payment_ippgateway_webhook_id'])) {
			$data['webhook_id'] = $this->request->post['payment_ippgateway_webhook_id'];
		} else {
			$data['webhook_id'] = $this->config->get('payment_ippgateway_webhook_id');
		}

		if (isset($this->request->post['payment_ippgateway_debug'])) {
			$data['debug'] = $this->request->post['payment_ippgateway_debug'];
		} else {
			$data['debug'] = $this->config->get('payment_ippgateway_debug');
		}
								
		if (isset($this->request->post['payment_ippgateway_transaction_method'])) {
			$data['transaction_method'] = $this->request->post['payment_ippgateway_transaction_method'];
		} else {
			$data['transaction_method'] = $this->config->get('payment_ippgateway_transaction_method');
		}
		
		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['payment_ippgateway_geo_zone_id'])) {
			$data['geo_zone_id'] = $this->request->post['payment_ippgateway_geo_zone_id'];
		} else {
			$data['geo_zone_id'] = $this->config->get('payment_ippgateway_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['payment_ippgateway_status'])) {
			$data['status'] = $this->request->post['payment_ippgateway_status'];
		} else {
			$data['status'] = $this->config->get('payment_ippgateway_status');
		}

		if (isset($this->request->post['payment_ippgateway_sort_order'])) {
			$data['sort_order'] = $this->request->post['payment_ippgateway_sort_order'];
		} else {
			$data['sort_order'] = $this->config->get('payment_ippgateway_sort_order');
		}
		
		if (isset($this->request->post['payment_ippgateway_currency_code'])) {
			$data['currency_code'] = $this->request->post['payment_ippgateway_currency_code'];
		} elseif ($this->config->get('payment_ippgateway_currency_value')) {
			$data['currency_code'] = $this->config->get('payment_ippgateway_currency_code');
		} else {
			$data['currency_code'] = 'USD';
		}
		
		if (isset($this->request->post['payment_ippgateway_currency_value'])) {
			$data['currency_value'] = $this->request->post['payment_ippgateway_currency_value'];
		} elseif ($this->config->get('payment_ippgateway_currency_value')) {
			$data['currency_value'] = $this->config->get('payment_ippgateway_currency_value');
		} else {
			$data['currency_value'] = '1';
		}
		
		if (isset($this->request->post['payment_ippgateway_card_currency_code'])) {
			$data['card_currency_code'] = $this->request->post['payment_ippgateway_card_currency_code'];
		} elseif ($this->config->get('payment_ippgateway_card_currency_value')) {
			$data['card_currency_code'] = $this->config->get('payment_ippgateway_card_currency_code');
		} else {
			$data['card_currency_code'] = 'USD';
		}
		
		if (isset($this->request->post['payment_ippgateway_card_currency_value'])) {
			$data['card_currency_value'] = $this->request->post['payment_ippgateway_card_currency_value'];
		} elseif ($this->config->get('payment_ippgateway_card_currency_value')) {
			$data['card_currency_value'] = $this->config->get('payment_ippgateway_card_currency_value');
		} else {
			$data['card_currency_value'] = '1';
		}
						
		if (isset($this->request->post['payment_ippgateway_setting'])) {
			$data['setting'] = array_replace_recursive((array)$data['setting'], (array)$this->request->post['payment_ippgateway_setting']);
		} else {
			$data['setting'] = array_replace_recursive((array)$data['setting'], (array)$this->config->get('payment_ippgateway_setting'));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
					
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/ippgateway/payment/ippgateway', $data));
	}
	
	public function save(): void {
		$this->load->language('extension/ippgateway/payment/ippgateway');
		
		$this->load->model('extension/ippgateway/payment/ippgateway');
		
		if (!$this->user->hasPermission('modify', 'extension/ippgateway/payment/ippgateway')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		
		// Setting 		
		$_config = new \Opencart\System\Engine\Config();
		$_config->addPath(DIR_EXTENSION . 'ippgateway/system/config/');
		$_config->load('ippgateway');
		
		$setting = $_config->get('ippgateway_setting');

        // Todo: Some testing of valid credentials for the gateway.

		if (!$this->error) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_ippgateway', $this->request->post);
			
			$data['success'] = $this->language->get('success_save');
		}
		
		$data['error'] = $this->error;
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
	}
		
	public function disconnect(): void {
		$this->load->model('setting/setting');
		
		$setting = $this->model_setting_setting->getSetting('payment_ippgateway');
						
		$setting['payment_ippgateway_client_id'] = '';
		$setting['payment_ippgateway_secret'] = '';
		$setting['payment_ippgateway_merchant_id'] = '';
		$setting['payment_ippgateway_webhook_id'] = '';
		
		$this->model_setting_setting->editSetting('payment_ippgateway', $setting);
		
		$data['error'] = $this->error;
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
	}
		
	public function callback(): void {
		if (isset($this->request->post['environment']) && isset($this->request->post['authorization_code']) && isset($this->request->post['shared_id']) && isset($this->request->post['seller_nonce'])) {
			$this->session->data['environment'] = $this->request->post['environment'];
			$this->session->data['authorization_code'] = $this->request->post['authorization_code'];
			$this->session->data['shared_id'] = $this->request->post['shared_id'];
			$this->session->data['seller_nonce'] = $this->request->post['seller_nonce'];
		}
		
		$data['error'] = $this->error;
				
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
    }
	
	public function install() {
		$this->load->model('setting/setting');
		
		$this->model_setting_setting->editValue('config', 'config_session_samesite', 'Lax');
	}
					
	private function token($length = 32): string {
		// Create random token
		$string = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	
		$max = strlen($string) - 1;
	
		$token = '';
	
		for ($i = 0; $i < $length; $i++) {
			$token .= $string[mt_rand(0, $max)];
		}	
	
		return $token;
	}
}