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

namespace Evoweb\EwLlxml2xliff\File;

use Evoweb\EwLlxml2xliff\Localization\Parser\LocallangXmlParser;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Conversion of locallang*.[xml|php] files to locallang.xlf.
 */
class Converter
{
    protected string $extension = '';

    public function setExtension(string $extension): void
    {
        $this->extension = $extension;
    }

    /**
     * Function to convert llxml files
     *
     * @param string $sourceFile Absolute path to the selected ll-XML file
     */
    public function writeXmlAsXlfFilesInPlace(string $sourceFile): string
    {
        $sourceFile = ExtensionManagementUtility::extPath($this->extension) . $sourceFile;

        if (!@is_file($sourceFile)) {
            return 'File ' . $sourceFile . ' does not exists!';
        }

        $fileCheckResult = $this->checkLanguageFilename($sourceFile);
        if (!empty($fileCheckResult)) {
            return $fileCheckResult;
        }

        $sourceFileOriginal = $sourceFile;
        $basename = basename($sourceFile);
        $dirname = dirname($sourceFile);
        if (($position = strpos($basename, '.')) == 2) {
            $sourceFileOriginal = $dirname . '/' . substr($basename, $position + 1);
        }
        $languages = $this->getAvailableTranslations($sourceFile);
        $errors = [];
        foreach ($languages as $langKey) {
            $newFileName = $dirname . '/' . $this->localizedFileRef($sourceFileOriginal, $langKey);
            if (@is_file($newFileName)) {
                $errors[] = 'ERROR: Output file "' . $newFileName . '" already exists!';
            }
        }

        if (!empty($errors)) {
            return implode('<br />', $errors);
        }

        $output = '';
        foreach ($languages as $langKey) {
            $newFileName = $dirname . '/' . $this->localizedFileRef($sourceFileOriginal, $langKey);
            $output .= $this->writeNewXliffFile($sourceFile, $newFileName, $langKey) . '<br />' . LF;
        }
        return $output;
    }

    /**
     * Function to convert php language files
     *
     * @param string $sourceFile Absolute path to the selected ll-XML file
     */
    public function writePhpAsXlfFilesInPlace(string $sourceFile): string
    {
        return $this->writeXmlAsXlfFilesInPlace($sourceFile);
    }

    /**
     * Checking for a valid locallang*.xml filename.
     *
     * @param string $xmlFile Absolute reference to the ll-XML locallang file
     *
     * @return string Empty (false) return value means "OK" while otherwise is an error string
     */
    protected function checkLanguageFilename(string $xmlFile): string
    {
        $result = '';
        if (!str_contains($xmlFile, 'locallang')) {
            $result = 'ERROR: Filename didn\'t contain "locallang".';
        }
        return $result;
    }

    /**
     * @param string $languageFile Absolute reference to the base locallang file
     *
     * @return array
     */
    protected function getAvailableTranslations(string $languageFile): array
    {
        if (strpos($languageFile, '.xml')) {
            $ll = $this->xml2array(file_get_contents($languageFile));
            $languages = isset($ll['data']) ? array_keys($ll['data']) : [];
        } else {
            require($languageFile);
            $languages = isset($LOCAL_LANG) ? array_keys($LOCAL_LANG) : [];
        }

        if (empty($languages)) {
            throw new \RuntimeException('data section not found in "' . $languageFile . '"', 1314187884);
        }

        return $languages;
    }

    /**
     * Returns localized fileRef ([langkey].locallang*.xml)
     *
     * @param string $fileRef Filename/path of a 'locallang*.xml' file
     * @param string $lang Language key
     *
     * @return string Input filename with a '[lang-key].locallang*.xml' name if $this->lang is not 'default'
     */
    protected function localizedFileRef(string $fileRef, string $lang): string
    {
        $path = '';
        if (str_ends_with($fileRef, '.xml') || str_ends_with($fileRef, '.php')) {
            $languagePrefix = $lang === 'default' ? '' : $lang . '.';
            $path = $languagePrefix . pathinfo($fileRef, PATHINFO_FILENAME) . '.xlf';
        }
        return $path;
    }

    /**
     * Processing of the submitted form; Will create and write the XLIFF file and tell the new file name.
     *
     * @param string $xmlFile Absolute path to the locallang.xml file to convert
     * @param string $newFileName The new file name to write to (absolute path, .xlf ending)
     * @param string $langKey The language key
     *
     * @return string HTML text string message
     */
    protected function writeNewXliffFile(string $xmlFile, string $newFileName, string $langKey): string
    {
        $xml = $this->generateFileContent($xmlFile, $langKey, $newFileName);

        $result = '';
        if (!@file_exists($newFileName)) {
            GeneralUtility::writeFile($newFileName, $xml);
            $result = str_replace(Environment::getComposerRootPath(), '', $newFileName);
        }

        return $result;
    }

    protected function generateFileContent(string $xmlFile, string $langKey, string $newFileName): string
    {
        // Initialize variables:
        $LOCAL_LANG = $this->getCombinedTranslationFileContent($xmlFile);
        $originalFile = $this->getOriginalFileName($newFileName, $langKey);

        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">';
        $xml[] = '  <file source-language="en"' . ($langKey !== 'default' ? ' target-language="' . $langKey . '"' : '')
            . ' datatype="plaintext" original="' . $originalFile . '" date="' . gmdate('Y-m-d\TH:i:s\Z')
            . '" product-name="' . $this->extension . '">';
        $xml[] = '    <body>';

        foreach ($LOCAL_LANG[$langKey] as $key => $data) {
            $xml[] = (string)(new TransUnit($data, $key, $langKey, $LOCAL_LANG));
        }

        $xml[] = '    </body>';
        $xml[] = '  </file>';
        $xml[] = '</xliff>';

        return implode(LF, $xml);
    }

    protected function getOriginalFileName(string $filename, string $langKey): string
    {
        $currentTargetName = str_replace($langKey . '.', '', pathinfo($filename, PATHINFO_FILENAME));
        return 'EXT:' . $this->extension . '/Resources/Private/Language/' . $currentTargetName . '.xlf';
    }

    /**
     * Reads/Requires locallang files and returns raw $LOCAL_LANG array
     *
     * @param string $languageFile Absolute reference to the ll-XML locallang file.
     *
     * @return array LOCAL_LANG array from ll-XML file (with all possible sub-files for languages included)
     */
    protected function getCombinedTranslationFileContent(string $languageFile): array
    {
        $LOCAL_LANG = [];
        if (strpos($languageFile, '.xml')) {
            $ll = $this->xml2array(file_get_contents($languageFile));
            $includedLanguages = array_keys($ll['data']);

            foreach ($includedLanguages as $langKey) {
                /** @var $parser LocallangXmlParser */
                $parser = GeneralUtility::makeInstance(LocallangXmlParser::class);
                $localLangContent = $parser->getParsedData($languageFile, $langKey);
                unset($parser);
                $LOCAL_LANG[$langKey] = $localLangContent[$langKey];
            }
        } else {
            require($languageFile);
            $includedLanguages = isset($LOCAL_LANG) ? array_keys($LOCAL_LANG) : [];
        }

        if (empty($includedLanguages)) {
            throw new \RuntimeException('data section not found in "' . $languageFile . '"', 1314187884);
        }

        return $LOCAL_LANG;
    }

    /**
     * Converts an XML string to a PHP array.
     * This is the reverse function of GeneralUtility::array2xml()
     * This is a wrapper for xml2arrayProcess that adds a two-level cache
     *
     * @param string $string XML content to convert into an array
     * @param string $namespacePrefix The tag-prefix resolve, e.g. a namespace like "T3:"
     * @param bool $reportDocTag If set, the document tag will be set in the key "_DOCUMENT_TAG" of the output array
     *
     * @return array|string If the parsing had errors, a string with the error message is returned.
     *         Otherwise, an array with the content.
     *
     * @see GeneralUtility::array2xml(),GeneralUtility::xml2arrayProcess()
     */
    protected function xml2array(string $string, string $namespacePrefix = '', bool $reportDocTag = false): array|string
    {
        return GeneralUtility::xml2array($string, $namespacePrefix, $reportDocTag);
    }
}
