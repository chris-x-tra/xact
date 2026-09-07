<?php

declare(strict_types=1);

namespace OCA\Xact\AppInfo;

$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
	require_once $vendorAutoload;
}

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Xact\Listener\LoadAdditionalScriptsListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'xact';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		// Wird nur ausgelöst, wenn die Files-App tatsächlich gerendert wird –
		// dadurch landet unser JS nicht auf jeder Seite, sondern nur im Filebrowser.
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
