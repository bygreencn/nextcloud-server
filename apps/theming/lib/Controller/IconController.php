<?php
/**
 * @copyright Copyright (c) 2016 Julius Haertl <jus@bitgrid.net>
 *
 * @author Julius Haertl <jus@bitgrid.net>
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
namespace OCA\Theming\Controller;

use OCA\Theming\IconBuilder;
use OCA\Theming\ThemingDefaults;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IRequest;
use OCA\Theming\Util;
use OCP\IConfig;
use OCP\Files\NotFoundException;

class IconController extends Controller {
	/** @var ThemingDefaults */
	private $themingDefaults;
	/** @var Util */
	private $util;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var IConfig */
	private $config;
	/** @var IconBuilder */
	private $iconBuilder;
	/** @var IAppData */
	private $appData;

	/**
	 * IconController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param ThemingDefaults $themingDefaults
	 * @param Util $util
	 * @param ITimeFactory $timeFactory
	 * @param IConfig $config
	 * @param IconBuilder $iconBuilder
	 * @param IAppData $appData
	 */
	public function __construct(
		$appName,
		IRequest $request,
		ThemingDefaults $themingDefaults,
		Util $util,
		ITimeFactory $timeFactory,
		IConfig $config,
		IconBuilder $iconBuilder,
		IAppData $appData
	) {
		parent::__construct($appName, $request);

		$this->themingDefaults = $themingDefaults;
		$this->util = $util;
		$this->timeFactory = $timeFactory;
		$this->config = $config;
		$this->iconBuilder = $iconBuilder;
		$this->appData = $appData;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param $app string app name
	 * @param $image string image file name (svg required)
	 * @return FileDisplayResponse
	 */
	public function getThemedIcon($app, $image) {
		$iconFile = $this->getCachedImage("icon-" . $app . '-' . str_replace("/","_",$image));
		if ($iconFile === null) {
			$imageFile = $this->util->getAppImage($app, $image);
			$svg = file_get_contents($imageFile);
			$color = $this->util->elementColor($this->themingDefaults->getMailHeaderColor());
			$svg = $this->util->colorizeSvg($svg, $color);
			$iconFile = $this->setCachedImage("icon-" . $app . '-' . str_replace("/","_",$image), $svg);
		}
		$response = new FileDisplayResponse($iconFile, Http::STATUS_OK, ['Content-Type' => 'image/svg+xml']);
		$response->cacheFor(86400);
		$response->addHeader('Expires', date(\DateTime::RFC2822, $this->timeFactory->getTime()));
		$response->addHeader('Pragma', 'cache');
		return $response;
	}

	/**
	 * Return a 32x32 favicon as png
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param $app string app name
	 * @return FileDisplayResponse|DataDisplayResponse
	 */
	public function getFavicon($app = "core") {
		$iconFile = $this->getCachedImage('favIcon-' . $app);
		if($iconFile === null && $this->themingDefaults->shouldReplaceIcons()) {
			$icon = $this->iconBuilder->getFavicon($app);
			$iconFile = $this->setCachedImage('favIcon-' . $app, $icon);
		}
		if ($this->themingDefaults->shouldReplaceIcons()) {
			$response = new FileDisplayResponse($iconFile, Http::STATUS_OK, ['Content-Type' => 'image/x-icon']);
		} else {
			$response = new DataDisplayResponse(null, Http::STATUS_NOT_FOUND);
		}
		$response->cacheFor(86400);
		$response->addHeader('Expires', date(\DateTime::RFC2822, $this->timeFactory->getTime()));
		$response->addHeader('Pragma', 'cache');
		return $response;
	}

	/**
	 * Return a 512x512 icon for touch devices
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param $app string app name
	 * @return FileDisplayResponse|DataDisplayResponse
	 */
	public function getTouchIcon($app = "core") {
		$iconFile = $this->getCachedImage('touchIcon-' . $app);
		if ($iconFile === null && $this->themingDefaults->shouldReplaceIcons()) {
			$icon = $this->iconBuilder->getTouchIcon($app);
			$iconFile = $this->setCachedImage('touchIcon-' . $app, $icon);
		}
		if ($this->themingDefaults->shouldReplaceIcons()) {
			$response = new FileDisplayResponse($iconFile, Http::STATUS_OK, ['Content-Type' => 'image/png']);
		} else {
			$response = new DataDisplayResponse(null, Http::STATUS_NOT_FOUND);
		}
		$response->cacheFor(86400);
		$response->addHeader('Expires', date(\DateTime::RFC2822, $this->timeFactory->getTime()));
		$response->addHeader('Pragma', 'cache');
		return $response;
	}

	/**
	 * @param $filename
	 * @return \OCP\Files\SimpleFS\ISimpleFile
	 */
	private function getCachedImage($filename) {
		$currentFolder = $this->getCacheFolder();
		if($currentFolder->fileExists($filename)) {
			return $currentFolder->getFile($filename);
		} else {
			return null;
		}
	}

	/**
	 * @param $filename
	 * @param $data
	 * @return ISimpleFile
	 */
	private function setCachedImage($filename, $data) {
		$currentFolder = $this->getCacheFolder();
		$file = $currentFolder->fileExists($filename);
		if ($file) {
			$currentFolder->getFile($filename)->putContent($data);
		} else {
			$currentFolder->newFile($filename)->putContent($data);
		}
		return $currentFolder->getFile($filename);
	}

	/**
	 * @return ISimpleFolder
	 */
	private function getCacheFolder() {

		$cacheBusterValue = $this->config->getAppValue('theming', 'cachebuster', '0');
		try {
			$currentFolder = $this->appData->getFolder($cacheBusterValue);
		} catch (NotFoundException $e) {
			$currentFolder = $this->appData->newFolder($cacheBusterValue);
			// cleanup old folders
			$folders = $this->appData->getDirectoryListing();
			foreach ($folders as $folder) {
				if ($folder->getName() !== $currentFolder->getName()) {
					$folder->delete();
				}
			}
		}
		return $currentFolder;
	}

}