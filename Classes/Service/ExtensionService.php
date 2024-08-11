<?php

declare(strict_types=1);

/*
 * This file is developed by evoWeb.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Evoweb\EwLlxml2xliff\Service;

use Evoweb\EwLlxml2xliff\File\Converter;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extensionmanager\Utility\ListUtility;

readonly class ExtensionService
{
    public function __construct(
        protected ListUtility $listUtility,
        protected Converter $converter,
    ) {}

    public function getLocalExtensions(): array
    {
        $availableExtensions = $this->listUtility->getAvailableExtensions();
        $availableExtensions = $this->listUtility->enrichExtensionsWithEmConfInformation($availableExtensions);

        $extensions = array_filter(
            $availableExtensions,
            function (array $extension) {
                if ($extension['type'] !== 'Local' || ($extension['key'] ?? '') === '') {
                    return false;
                }
                $extensionsWithFileToConvert = [];
                if (count($this->getFilesOfExtension($extension['key']))) {
                    $extensionsWithFileToConvert[] = $extension;
                }
                return count($extensionsWithFileToConvert);
            },
            ARRAY_FILTER_USE_BOTH
        );
        ksort($extensions);
        return $extensions;
    }

    /**
     * Gather files for given extension key that need to be converted
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
        $xliffFileName = preg_replace('#\.(xml|php)$#', '.xlf', $extensionPath . $filePath);
        return @file_exists($xliffFileName);
    }
}
