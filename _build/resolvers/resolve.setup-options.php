<?php

/**
 * @var modX|xPDO $modx
 */
$modx =& $transport->xpdo;
$success = false;

switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:

        // load package
        $modelPath = $modx->getOption('simplecart.core_path', null, $modx->getOption('core_path') . 'components/simplecart/') . 'model/';
        $modx->addPackage('simplecart', $modelPath);

        /** @var simpleCartMethod $method */
        $method = $modx->getObject('simpleCartMethod', array('name' => 'paypal', 'type' => 'payment'));
		if(empty($method) || !is_object($method)) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[SimpleCart] Failed to find newly created record for the PayPal payment method');
            return false;
        }

        $configs = array(
            'currency',
            'username',
            'password',
            'signature',
            'noshipping',
            'usesandbox',
        );

        foreach ($configs as $key) {
            if (isset($options[$key])) {

                /** @var simpleCartMethodProperty $property */
                $property = $modx->getObject('simpleCartMethodProperty', array('method' => $method->get('id'), 'name' => $key));
                if (!empty($property) && is_object($property)) {

                    $property->set('value', $options[$key]);
                    $property->save();
                }
            }
        }

        $success = true;
        break;

    case xPDOTransport::ACTION_UNINSTALL:

        break;
}

return $success;