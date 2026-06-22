<?php

declare(strict_types=1);

/*
 * This file is developed by evoWeb.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Evoweb\EwLlxml2xliff\Service;

use Evoweb\EwLlxml2xliff\File\Converter;
use TYPO3\CMS\Core\Package\Package;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Package\VirtualAppPackage;
use TYPO3\CMS\Core\SystemResource\Publishing\SystemResourcePublisherInterface;
use TYPO3\CMS\Core\SystemResource\SystemResourceFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extensionmanager\Utility\ListUtility;

readonly class ExtensionService
{
    public function __construct(
        protected PackageManager $packageManager,
        protected SystemResourceFactory $resourceFactory,
        protected SystemResourcePublisherInterface $resourcePublisher,
        protected ListUtility $listUtility,
        protected Converter $converter,
    ) {}

    /**
     * @return array<string, array<string, string>>
     */
    public function getLocalExtensions(): array
    {
        $availablePackages = $this->packageManager->getAvailablePackages();

        $localPackages = array_filter(array_map(
            function (Package $package): ?array {
                $metaData = $package->getPackageMetaData();
                if (
                    $package instanceof VirtualAppPackage
                    || $package->getPackageKey() === ''
                    || $metaData->isFrameworkType()
                    || count($this->getFilesOfExtension($package->getPackageKey())) === 0
                ) {
                    return null;
                }
                $icon = $package->getResources()->getPackageIcon();
                return [
                    'packagePath' => $package->getPackagePath(),
                    'type' => 'Local',
                    'key' => $package->getPackageKey(),
                    'icon' => $icon ? (string)$this->resourcePublisher->generateUri($this->resourceFactory->createPublicResource($icon), null) : '',
                    'title' => $metaData->getTitle(),
                    'description' => $metaData->getDescription(),
                    'files' => count($this->getFilesOfExtension($package->getPackageKey())) > 0,
                ];
            },
            $availablePackages,
        ));
        ksort($localPackages);

        return $this->listUtility->enrichExtensionsWithEmConfInformation($localPackages);
    }

    /**
     * Gather files for a given extension key that need to be converted
     * @return array<string, array<string, string>>
     */
    public function getFilesOfExtension(string $extensionKey): array
    {
        if (!ExtensionManagementUtility::isLoaded($extensionKey)) {
            return [];
        }
        $extensionPath = ExtensionManagementUtility::extPath($extensionKey);
        $files = GeneralUtility::getAllFilesAndFoldersInPath([], $extensionPath, 'php,xml');

        $result = [];

        foreach ($files as $file) {
            if ($this->isLanguageFile($file) && !$this->xliffFileAlreadyExists($extensionPath, $file)) {
                $filename = GeneralUtility::removePrefixPathFromList([$file], $extensionPath)[0];
                $result[$filename] = [
                    'filename' => $filename,
                ];
            }
        }

        ksort($result);

        return $result;
    }

    protected function isLanguageFile(string $filePath): bool
    {
        return str_contains($filePath, 'Resources/Private/Language/');
    }

    /**
     * @param array<string, array<string, string>> $files
     * @return array<string, array<string, array<string, string>>|bool|string>
     */
    public function convertLanguageFile(string $selectedExtension, string $selectedFile, array $files): array
    {
        $wasConvertedPreviously = false;
        $fileConvertedSuccessfully = false;
        $messages = '';

        $extensionPath = ExtensionManagementUtility::extPath($selectedExtension);
        if ($this->xliffFileAlreadyExists($extensionPath, $selectedFile)) {
            $wasConvertedPreviously = true;
        } else {
            $this->converter->setExtension($selectedExtension);
            if (str_contains($selectedFile, '.xml')) {
                $messages = $this->converter->writeXmlAsXlfFilesInPlace($selectedFile);
            } else {
                $messages = $this->converter->writePhpAsXlfFilesInPlace($selectedFile);
            }

            if (!str_contains($messages, 'ERROR')) {
                $fileConvertedSuccessfully = true;
            }
            unset($files[$selectedFile]);
        }

        return [
            'wasConvertedPreviously' => $wasConvertedPreviously,
            'fileConvertedSuccessfully' => $fileConvertedSuccessfully,
            'messages' => $messages,
            'files' => $files,
        ];
    }

    public function xliffFileAlreadyExists(string $extensionPath, string $filePath): bool
    {
        $xliffFileName = (string)preg_replace('#\.(xml|php)$#', '.xlf', $extensionPath . $filePath);
        return @file_exists($xliffFileName);
    }
}
