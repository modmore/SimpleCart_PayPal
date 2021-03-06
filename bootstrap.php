<?php
/* Get the core config */
if (!file_exists(dirname(__DIR__) . '/config.core.php')) {
    die('ERROR: missing '.dirname(__DIR__) . '/config.core.php file defining the MODX core path.');
}

echo "<pre>";
/* Boot up MODX */
echo "Loading modX...\n";
require_once dirname(__DIR__).'/config.core.php';
require_once MODX_CORE_PATH.'model/modx/modx.class.php';
$modx = new modX();
echo "Initializing manager...\n";
$modx->initialize('mgr');
$modx->getService('error','error.modError', '', '');

$componentPath = __DIR__;

$scPath = $modx->getOption('simplecart.core_path', null, MODX_CORE_PATH . 'components/simplecart/');
$SimpleCart = $modx->getService('simplecart','SimpleCart', $scPath . 'model/simplecart/');
if (!($SimpleCart instanceof SimpleCart)) {
    die('Could not load SimpleCart from ' . $scPath);
}

/* Namespaces */
if (!createObject('modNamespace',array(
    'name' => 'simplecart_paypal',
    'path' => $componentPath.'/core/components/simplecart_paypal/',
    'assets_path' => $componentPath.'/assets/components/simplecart_paypal/',
),'name', false)) {
    echo "Error creating namespace simplecart_paypal.\n";
}
/* Path settings 1 */
if (!createObject('modSystemSetting', array(
    'key' => 'simplecart_paypal.core_path',
    'value' => $componentPath.'/core/components/simplecart_paypal/',
    'xtype' => 'textfield',
    'namespace' => 'simplecart_paypal',
    'area' => 'Paths',
    'editedon' => time(),
), 'key', false)) {
    echo "Error creating simplecart_paypal.core_path setting.\n";
}
if (!createObject('modSystemSetting', array(
    'key' => 'simplecart_paypal.assets_path',
    'value' => $componentPath.'/assets/components/simplecart_paypal/',
    'xtype' => 'textfield',
    'namespace' => 'simplecart_paypal',
    'area' => 'Paths',
    'editedon' => time(),
), 'key', false)) {
    echo "Error creating simplecart_paypal.assets_path setting.\n";
}

/* Fetch assets url */
$url = 'http';
if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on')) {
    $url .= 's';
}
$url .= '://'.$_SERVER["SERVER_NAME"];
if ($_SERVER['SERVER_PORT'] != '80') {
    $url .= ':'.$_SERVER['SERVER_PORT'];
}
$requestUri = $_SERVER['REQUEST_URI'];
$bootstrapPos = strpos($requestUri, '_bootstrap/');
$requestUri = rtrim(substr($requestUri, 0, $bootstrapPos), '/').'/';
$assetsUrl = "{$url}{$requestUri}assets/components/simplecart_paypal/";

if (!createObject('modSystemSetting', array(
    'key' => 'simplecart_paypal.assets_url',
    'value' => $assetsUrl,
    'xtype' => 'textfield',
    'namespace' => 'simplecart_paypal',
    'area' => 'Paths',
    'editedon' => time(),
), 'key', false)) {
    echo "Error creating simplecart_paypal.assets_url setting.\n";
}

// Payment method
if (!createObject('simpleCartMethod', array(
    'name' => 'paypal',
    'price_add' => null,
    'type' => 'payment',
    'sort_order' => $modx->getCount('simpleCartMethod') + 1,
), 'name', false)) {
    echo "Error creating simpleCartMethod object.\n";
}
$method = $modx->getObject('simpleCartMethod', ['name' => 'paypal']);
if (!$method) {
    die ('Failed to load or create simplecart_paypal payment method');
}
$methodId = $method->get('id');
$props = array(
    'currency' => 'USD',
    'username' => '',
    'password' => '',
    'signature' => '',
    'noshipping' => 1,
    'usesandbox' => 1,
);
foreach ($props as $key => $value) {
    createObject('simpleCartMethodProperty', [
        'method' => $methodId,
        'name' => $key,
        'value' => $value
    ], ['method', 'name'], false);
}

// Refresh the cache
$modx->cacheManager->refresh();

echo "Done.";

/**
 * Creates an object.
 *
 * @param string $className
 * @param array $data
 * @param string $primaryField
 * @param bool $update
 * @return bool
 */
function createObject ($className = '', array $data = array(), $primaryField = '', $update = true) {
    global $modx;
    /* @var xPDOObject $object */
    $object = null;

    /* Attempt to get the existing object */
    if (!empty($primaryField)) {
        if (is_array($primaryField)) {
            $condition = array();
            foreach ($primaryField as $key) {
                $condition[$key] = $data[$key];
            }
        }
        else {
            $condition = array($primaryField => $data[$primaryField]);
        }
        $object = $modx->getObject($className, $condition);
        if ($object instanceof $className) {
            if ($update) {
                $object->fromArray($data);
                return $object->save();
            } else {
                $condition = $modx->toJSON($condition);
                echo "Skipping {$className} {$condition}: already exists.\n";
                return true;
            }
        }
    }

    /* Create new object if it doesn't exist */
    if (!$object) {
        $object = $modx->newObject($className);
        $object->fromArray($data, '', true);
        return $object->save();
    }

    return false;
}
