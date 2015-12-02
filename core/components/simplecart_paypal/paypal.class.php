<?php

// require the class
require_once(dirname(__FILE__).'/lib/paypal.class.php');

class SimpleCartPaypalPaymentGateway extends SimpleCartGateway {

    /** @var phpPayPal $paypal */
    private $paypal;

    public function submit() {

        try {

            if(!$this->initPayPal()) { return false; }
            $this->modx->lexicon->load('simplecart:cart', 'simplecart:methods');

            $total = number_format($this->order->get('total'), 2, '.', ',');
            // set order total amount for PayPal
            $this->paypal->amount_total = $total;

            /** @var modChunk $chunk */
            $content = $this->modx->lexicon('simplecart.methods.yourorderat');
            $chunk = $this->modx->newObject('modChunk');
            $chunk->setCacheable(false);
            $chunk->setContent($content);
            $description = $chunk->process();

            // add order as item to appear in PayPal's summary
            $this->paypal->add_item($description, '', 1, 0, $total);

            // Perform the payment
            $expressCheckout = $this->paypal->set_express_checkout();
            if($expressCheckout) {

                $token = $this->paypal->Response['TOKEN'];

                $this->order->addLog('PayPal Token', $token);
                $this->order->save();
            }

            if(isset($this->paypal->Error) && !empty($this->paypal->Error) && isset($this->paypal->Error['SHORTMESSAGE'])) {

                $msg = 'Error: '.$this->paypal->Error['ERRORCODE'];
                $msg .= ' '.$this->paypal->Error['SHORTMESSAGE'];
                $msg .= ' '.$this->paypal->Error['LONGMESSAGE'];

                throw new Exception($msg);
            }

            @session_write_close();
            $this->paypal->set_express_checkout_successful_redirect();
            exit();
        }
        catch(Exception $e) {

            $this->order->addLog('PayPal Failure', $e->getMessage());
            $this->order->set('status', 'payment_failed');
            $this->order->save();

            $this->setRedirectUrl($this->getRedirectUrl(), array('error' => 'true'));
        }

        return false;
    }

    public function verify() {
        if(!$this->initPayPal()) {
            $this->order->addLog('PayPal Error', 'Could not initiate connection to PayPal');
            $this->order->save();
            return false;
        }

        if($this->hasProperty('token')) {
            $token = $this->order->getLog('PayPal Token');
            if ($token == $this->getProperty('token')) {

                // set token and payer id
                $this->paypal->token = $this->getProperty('token');
                $this->paypal->payer_id = $this->getProperty('PayerID');

                // set total amount
                $total = number_format($this->order->get('total'), 2, '.', ',');
                $this->paypal->amount_total = $total;

                $succeeded = $this->paypal->do_express_checkout_payment();
                $response = array_change_key_case($this->paypal->Response, CASE_LOWER);
                if($succeeded && strtolower($response['ack']) == 'success') {

                    // log the finishing status
                    $this->order->addLog('PayPal Payer ID', $response['payerid']);
                    $this->order->addLog('PayPal Status', 'Returned');
                    $this->order->setStatus('finished');
                    $this->order->save();

                    return true;
                }
                else {
                    $this->order->addLog('PayPal Payment Status', $response['l_longmessage0']);
                    $this->order->setStatus('payment_failed');
                    $this->order->save();
                }
            }
            else {
                $this->order->addLog('PayPal Return Error', 'Expected token "' . $token . '", got "' . htmlentities($this->getProperty('token'), ENT_QUOTES, 'UTF-8'));
                $this->order->save();
            }
        }
        else {
            $this->order->addLog('PayPal Return Error', 'No token specified in return from PayPal.');
            $this->order->save();
        }

        return false;
    }

    /** CUSTOM METHODS **/

    private function initPayPal() {

        // get properties
        $api_sandbox = (boolean) $this->getProperty('usesandbox', 0, 'isset');
        $api_shipping = (boolean) $this->getProperty('shipping', 0, 'isset');
        $api_username = $this->getProperty('username');
        $api_password = $this->getProperty('password');
        $api_signature = $this->getProperty('signature');

        // figure out the currency based on the new currencies and fall back on the properties
        $api_currency = $this->simplecart->currency->get('name');
        if(empty($api_currency)) {
            $api_currency = $this->getProperty('currency', 'EUR');
        }

        // because Sandbox only accepts USD
        if($api_sandbox) { $api_currency = 'USD'; }

        // proxy settings
		$api_proxy = (boolean) $this->getProperty('useproxy', 0, 'isset');
		$api_proxyhost = $this->getProperty('proxyhost', '');
		$api_proxyport = $this->getProperty('proxyport', '');

        if(empty($api_username) || empty($api_password) || empty($api_signature)) {
			$this->modx->log(modX::LOG_LEVEL_ERROR, '[SimpleCart] PayPal: no username, password or signature entered in the payment configuration!');
            return false;
		}

		$this->paypal = new phpPayPal(array(
			'api_username' => $api_username,
			'api_password' => $api_password,
			'api_signature' => $api_signature,
			'use_proxy' => $api_proxy,
			'proxy_host' => $api_proxyhost,
			'proxy_port' => $api_proxyport,
			'return_url' => $this->getRedirectUrl(),
			'cancel_url' => $this->getRedirectUrl(),
		), $api_sandbox);

		// (required)
		$this->paypal->ip_address = $_SERVER['REMOTE_ADDR'];
		$this->paypal->currency_code = $api_currency;
		$this->paypal->no_shipping = !$api_shipping; // it's logic!
		$this->paypal->user_action = 'commit';

        return true;
    }
}