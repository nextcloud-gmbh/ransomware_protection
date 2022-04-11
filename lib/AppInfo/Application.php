<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\RansomwareProtection\AppInfo;

use OC\Files\Filesystem;
use OCA\RansomwareProtection\Analyzer;
use OCA\RansomwareProtection\Notification\Notifier;
use OCA\RansomwareProtection\StorageWrapper;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Storage\IStorage;
use OCP\Notification\IManager;
use OCP\Util;

class Application extends App implements IBootstrap {
	public function __construct() {
		parent::__construct('ransomware_protection');
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
		Util::connectHook('OC_Filesystem', 'preSetup', $this, 'addStorageWrapper');
		$context->injectFn(\Closure::fromCallable([$this, 'registerNotificationNotifier']));
	}

	/**
	 * @internal
	 */
	public function addStorageWrapper() {
		// Needs to be added as the first layer
		Filesystem::addStorageWrapper('ransomware_protection', [$this, 'addStorageWrapperCallback'], -10);
	}

	/**
	 * @internal
	 * @param string $mountPoint
	 * @param IStorage $storage
	 * @return StorageWrapper|IStorage
	 */
	public function addStorageWrapperCallback($mountPoint, IStorage $storage) {
		if (!\OC::$CLI && !$storage->instanceOfStorage('OCA\Files_Sharing\SharedStorage')) {
			/** @var Analyzer $analyzer */
			$analyzer = $this->getContainer()->query(Analyzer::class);
			return new StorageWrapper([
				'storage' => $storage,
				'mountPoint' => $mountPoint,
				'analyzer' => $analyzer,
			]);
		}

		return $storage;
	}

	protected function registerNotificationNotifier(IManager $manager) {
		$manager->registerNotifierService(Notifier::class);
	}
}
