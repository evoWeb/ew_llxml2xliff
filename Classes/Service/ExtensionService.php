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

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extensionmanager\Utility\ListUtility;

class ExtensionService
{
    public function __construct(protected ListUtility $listUtility)
    {
    }

    public function getLocalExtensions(): array
    {
        $availableExtensions = $this->listUtility->getAvailableExtensions();
        $extensions = array_filter($availableExtensions, function ($extension) {
            $extensionsWithFileToConvert = [];
            if (count($this->getFilesOfExtension($extension['key'] ?? ''))) {
                $extensionsWithFileToConvert[] = $extension;
            }
            return $extension['type'] == 'Local' && count($extensionsWithFileToConvert);
        }, ARRAY_FILTER_USE_BOTH);
        ksort($extensions);
        return $extensions;
    }

    /**
     * Gather files that need to be converted
     *
     * @param string $extensionKey Extension for which to get list of files of
     *
     * @return array
     */
    public function getFilesOfExtension(string $extensionKey): array
    {
        $extensionPath = ExtensionManagementUtility::extPath($extensionKey);

        $xmlFiles = GeneralUtility::removePrefixPathFromList(
            GeneralUtility::getAllFilesAndFoldersInPath([], $extensionPath, 'xml', 0),
            $extensionPath
        );
        $phpFiles = GeneralUtility::removePrefixPathFromList(
            GeneralUtility::getAllFilesAndFoldersInPath([], $extensionPath, 'php', 0),
            $extensionPath
        );

        $result = [];

        if (is_array($xmlFiles)) {
            foreach ($xmlFiles as $file) {
                if ($this->isLanguageFile($file) && !$this->xliffFileAlreadyExists($extensionPath, $file)) {
                    $result[$file] = ['filename' => $file];
                }
            }
        }

        if (is_array($phpFiles)) {
            foreach ($phpFiles as $file) {
                if ($this->isLanguageFile($file) && !$this->xliffFileAlreadyExists($extensionPath, $file)) {
                    $result[$file] = ['filename' => $file];
                }
            }
        }

        if (!empty($result)) {
            ksort($result);
        }

        return $result;
    }

    protected function isLanguageFile(string $filePath): bool
    {
        return str_contains($filePath, 'locallang');
    }

    public function xliffFileAlreadyExists(string $extensionPath, string $filePath): bool
    {
        $xliffFileName = preg_replace('#\.(xml|php)$#', '.xlf', $extensionPath . $filePath);
        return file_exists($xliffFileName);
    }
}
