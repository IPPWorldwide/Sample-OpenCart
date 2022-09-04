<?php
namespace Opencart\Admin\Model\Extension\IPPGateway\Payment;
class IPPGateway extends \Opencart\System\Engine\Model {
			
	public function log(array $data = [], string $title = ''): void {
		if ($this->config->get('payment_ippgateway_debug')) {
			$log = new \Opencart\System\Library\Log('ippgateway.log');
			$log->write('IPPGateway debug (' . $title . '): ' . json_encode($data));
		}
	}
}
