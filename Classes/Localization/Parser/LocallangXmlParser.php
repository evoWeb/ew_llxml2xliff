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

namespace Evoweb\EwLlxml2xliff\Localization\Parser;

use TYPO3\CMS\Core\Localization\Exception\FileNotFoundException;
use TYPO3\CMS\Core\Localization\Exception\InvalidXmlFileException;
use TYPO3\CMS\Core\Localization\Parser\AbstractXmlParser;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Parser for XML locallang file.
 */
class LocallangXmlParser extends AbstractXmlParser
{
    /**
     * Associative array of "filename => parsed data" pairs.
     */
    protected array $parsedTargetFiles = [];

    /**
     * Actually doing all the work of parsing an XML file
     *
     * @param string $sourcePath Source file path
     * @param string $languageKey Language key
     *
     * @return array
     *
     * @throws FileNotFoundException
     * @throws InvalidXmlFileException
     */
    public function getParsedData($sourcePath, $languageKey): array
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
     */
    protected function getParsedDataForElement(\SimpleXMLElement $bodyOfFileTag, string $element): array
    {
        $parsedData = [];
        $children = $bodyOfFileTag->children();
        if ($children->count() === 0) {
            // Check for externally-referenced resource:
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
     */
    protected function doParsingFromRoot(\SimpleXMLElement $root): array
    {
        return $this->doParsingFromRootForElement($root, 'source');
    }

    /**
     * Returns array representation of XLIFF data, starting from a root node.
     */
    protected function doParsingTargetFromRoot(\SimpleXMLElement $root): array
    {
        return $this->doParsingFromRootForElement($root, 'target');
    }

    /**
     * Returns array representation of XLIFF data, starting from a root node.
     */
    protected function doParsingFromRootForElement(\SimpleXMLElement $root, string $element): array
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
            if (isset($localizedBodyOfFileTag[0]) && $localizedBodyOfFileTag[0] instanceof \SimpleXMLElement) {
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
     * Returns parsed representation of XML file.
     *
     * Parses XML if it wasn't done before. Caches parsed data.
     */
    protected function getParsedTargetData(string $path): array
    {
        if (!isset($this->parsedTargetFiles[$path])) {
            $this->parsedTargetFiles[$path] = $this->parseXmlTargetFile($path);
        }
        return $this->parsedTargetFiles[$path];
    }

    /**
     * Reads and parses XML file and returns internal representation of data.
     */
    protected function parseXmlTargetFile(string $targetPath): array
    {
        $rootXmlNode = false;
        if (@file_exists($targetPath)) {
            $xmlContent = file_get_contents($targetPath);
            $rootXmlNode = simplexml_load_string($xmlContent, \SimpleXMLElement::class, LIBXML_NOWARNING);
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
