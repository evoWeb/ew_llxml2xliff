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

namespace Evoweb\EwLlxml2xliff\Localization\Parser;

use SimpleXMLElement;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Localization\Exception\FileNotFoundException;
use TYPO3\CMS\Core\Localization\Exception\InvalidXmlFileException;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Parser for an XML locallang file.
 */
class LocallangXmlParser
{
    protected string $sourcePath;

    protected string $languageKey;

    /**
     * Associative array of "filename => parsed data" pairs.
     * @var array<string, array<string, mixed>> $parsedTargetFiles
     */
    protected array $parsedTargetFiles = [];

    /**
     * Loads the current XML file before processing.
     *
     * @return array<string, array<string, string>> An array representing a parsed XML file (structure depends on concrete parser)
     * @throws InvalidXmlFileException
     */
    protected function parseXmlFile(): array
    {
        $xmlContent = file_get_contents($this->sourcePath);
        if ($xmlContent === false) {
            throw new InvalidXmlFileException(
                'The path provided does not point to an existing and accessible file.',
                1278155987
            );
        }
        $rootXmlNode = simplexml_load_string($xmlContent, SimpleXMLElement::class, LIBXML_NOWARNING);
        if ($rootXmlNode === false) {
            $xmlError = libxml_get_last_error();
            throw new InvalidXmlFileException(
                'The path provided does not point to an existing and accessible well-formed XML file. Reason: ' . $xmlError->message . ' in ' . $this->sourcePath . ', line ' . $xmlError->line,
                1278155988
            );
        }
        return $this->doParsingFromRoot($rootXmlNode);
    }

    /**
     * Checks if a localized file is found in the labels pack (e.g. a language pack was downloaded in the backend)
     * or if $sameLocation is set, then checks for a file located in "{language}.locallang.xlf" at the same directory
     *
     * @param string $fileRef Absolute file reference to a locallang file
     * @param string $language Language key
     * @param bool $sameLocation If TRUE, then locallang localization file name will be returned with the same directory as $fileRef
     * @return string Absolute path to the language file
     */
    protected function getLocalizedFileName(string $fileRef, string $language, bool $sameLocation = false): string
    {
        // If $fileRef is already prefixed with "[language key]" then we should return it as is
        $fileName = PathUtility::basename($fileRef);
        if (str_starts_with($fileName, $language . '.')) {
            return GeneralUtility::getFileAbsFileName($fileRef);
        }

        if ($sameLocation) {
            return GeneralUtility::getFileAbsFileName(str_replace($fileName, $language . '.' . $fileName, $fileRef));
        }

        // Analyze file reference
        if (str_starts_with($fileRef, Environment::getFrameworkBasePath() . '/')) {
            // Is system
            $validatedPrefix = Environment::getFrameworkBasePath() . '/';
        } elseif (str_starts_with($fileRef, Environment::getExtensionsPath() . '/')) {
            // Is local
            $validatedPrefix = Environment::getExtensionsPath() . '/';
        } else {
            $validatedPrefix = '';
        }
        if ($validatedPrefix) {
            // Divide file reference into extension key, directory (if any) and base name:
            [$extensionKey, $file_extPath] = explode('/', substr($fileRef, strlen($validatedPrefix)), 2);
            $temp = GeneralUtility::revExplode('/', $file_extPath, 2);
            if (count($temp) === 1) {
                array_unshift($temp, '');
            }
            // Add an empty first-entry if not there.
            [$file_extPath, $file_fileName] = $temp;
            // The filename is prefixed with "[language key]." because it prevents the llxmltranslate tool from detecting it.
            return Environment::getLabelsPath() . '/' . $language . '/' . $extensionKey . '/' . ($file_extPath ? $file_extPath . '/' : '') . $language . '.' . $file_fileName;
        }
        return '';
    }

    /**
     * Actually doing all the work of parsing an XML file
     *
     * @param string $sourcePath Source file path
     * @param string $languageKey Language key
     *
     * @return array<string, array<string, string>>
     *
     * @throws FileNotFoundException
     * @throws InvalidXmlFileException
     */
    public function getParsedData(string $sourcePath, string $languageKey): array
    {
        $this->sourcePath = $sourcePath;
        $this->languageKey = $languageKey;

        // Parse source
        $parsedSource = $this->parseXmlFile();

        // Parse target
        $localizedTargetPath = $this->getLocalizedFileName($sourcePath, $this->languageKey);
        $targetPath = $this->languageKey !== 'default' && @is_file($localizedTargetPath)
            ? $localizedTargetPath
            : $sourcePath;

        try {
            $parsedTarget = $this->getParsedTargetData($targetPath);
        } catch (InvalidXmlFileException) {
            $parsedTarget = $this->getParsedTargetData($sourcePath);
        }

        $LOCAL_LANG = [];
        $LOCAL_LANG[$languageKey] = $parsedSource;
        ArrayUtility::mergeRecursiveWithOverrule($LOCAL_LANG[$languageKey], $parsedTarget);
        return $LOCAL_LANG;
    }

    /**
     * Parse the given language key tag
     * @return array<string, array<string, string>>
     */
    protected function getParsedDataForElement(SimpleXMLElement $bodyOfFileTag, string $element): array
    {
        $parsedData = [];
        $children = $bodyOfFileTag->children();
        if ($children->count() === 0) {
            // Check for externally referenced resource:
            // <languageKey index="fr">EXT:yourext/path/to/localized/locallang.xml</languageKey>
            $reference = sprintf('%s', $bodyOfFileTag);
            if (str_ends_with($reference, '.xml')) {
                return $this->getParsedTargetData(GeneralUtility::getFileAbsFileName($reference));
            }
        }

        foreach ($children as $translationElement) {
            if ($translationElement->getName() === 'label') {
                $parsedData[(string)$translationElement['index']] = [
                    $element => (string)$translationElement,
                ];
            }
        }
        return $parsedData;
    }

    /**
     * Returns array representation of XLIFF data, starting from a root node.
     * @return array<string, array<string, string>>
     */
    protected function doParsingFromRoot(SimpleXMLElement $root): array
    {
        return $this->doParsingFromRootForElement($root, 'source');
    }

    /**
     * Returns array representation of XLIFF data, starting from a root node.
     * @return array<string, array<string, string>>
     */
    protected function doParsingTargetFromRoot(SimpleXMLElement $root): array
    {
        return $this->doParsingFromRootForElement($root, 'target');
    }

    /**
     * Returns array representation of XLIFF data, starting from a root node.
     * @return array<string, array<string, string>>
     */
    protected function doParsingFromRootForElement(SimpleXMLElement $root, string $element): array
    {
        // @extensionScannerIgnoreLine
        $bodyOfFileTag = $root->data->languageKey;
        if ($bodyOfFileTag === null) {
            throw new InvalidXmlFileException(
                'Invalid locallang.xml language file "' . PathUtility::stripPathSitePrefix($this->sourcePath) . '"',
                1487944884
            );
        }

        if ($element === 'source' || $this->languageKey === 'default') {
            $parsedData = $this->getParsedDataForElement($bodyOfFileTag, $element);
        } else {
            $parsedData = [];
        }

        if ($element === 'target') {
            // Check if the source llxml file contains localized records
            // @extensionScannerIgnoreLine
            $localizedBodyOfFileTag = $root->data->xpath('languageKey[@index=\'' . $this->languageKey . '\']');
            if (isset($localizedBodyOfFileTag[0]) && $localizedBodyOfFileTag[0] instanceof SimpleXMLElement) {
                $parsedDataTarget = $this->getParsedDataForElement($localizedBodyOfFileTag[0], $element);
                $mergedData = $parsedDataTarget + $parsedData;
                if ($this->languageKey === 'default') {
                    $parsedData = array_intersect_key($mergedData, $parsedData, $parsedDataTarget);
                } else {
                    $parsedData = array_intersect_key($mergedData, $parsedDataTarget);
                }
            }
        }

        return $parsedData;
    }

    /**
     * Returns parsed representation of an XML file.
     *
     * Parses XML if it wasn't done before. Caches parsed data.
     * @return array<string, array<string, string>>
     */
    protected function getParsedTargetData(string $path): array
    {
        if (!isset($this->parsedTargetFiles[$path])) {
            $this->parsedTargetFiles[$path] = $this->parseXmlTargetFile($path);
        }
        return $this->parsedTargetFiles[$path];
    }

    /**
     * Reads and parses an XML file and returns internal representation of data.
     * @return array<string, array<string, string>>
     */
    protected function parseXmlTargetFile(string $targetPath): array
    {
        $rootXmlNode = false;
        if (@file_exists($targetPath)) {
            $xmlContent = file_get_contents($targetPath);
            $rootXmlNode = simplexml_load_string($xmlContent, SimpleXMLElement::class, LIBXML_NOWARNING);
        }
        if ($rootXmlNode === false) {
            $xmlError = libxml_get_last_error();
            throw new InvalidXmlFileException(
                'The path provided does not point to existing and accessible well-formed XML file. Reason: '
                . $xmlError->message . ' in ' . $targetPath . ', line ' . $xmlError->line,
                1278155987
            );
        }
        return $this->doParsingTargetFromRoot($rootXmlNode);
    }
}
