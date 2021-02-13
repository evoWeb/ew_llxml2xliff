<?php

namespace Evoweb\EwLlxml2xliff\Utility;

/*
 *  Copyright notice
 *
 *  (c) 2011 Xavier Perseguers <xavier@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Conversion of locallang*.[xml|php] files to locallang.xlf.
 *
 * @author Xavier Perseguers <xavier@typo3.org>
 */
class Convert
{
    protected string $extension = '';

    public function setExtension(string $extension)
    {
        $this->extension = $extension;
    }

    /**
     * Function to convert llxml files
     *
     * @param string $sourceFile Absolute path to the selected ll-XML file
     *
     * @return string HTML content
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

        $languages = $this->getAvailableTranslations($sourceFile);
        $errors = [];
        foreach ($languages as $langKey) {
            $newFileName = dirname($sourceFile) . '/' . $this->localizedFileRef($sourceFile, $langKey);
            if (@is_file($newFileName)) {
                $errors[] = 'ERROR: Output file "' . $newFileName . '" already exists!';
            }
        }

        if (!empty($errors)) {
            return implode('<br />', $errors);
        }

        $output = '';
        foreach ($languages as $langKey) {
            $newFileName = dirname($sourceFile) . '/' . $this->localizedFileRef($sourceFile, $langKey);
            $output .= $this->writeNewXliffFile($sourceFile, $newFileName, $langKey) . '<br />';
        }
        return $output;
    }

    /**
     * Function to convert php language files
     *
     * @param string $sourceFile Absolute path to the selected ll-XML file
     *
     * @return string HTML content
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
        $basename = basename($xmlFile);

        $result = '';
        if (strpos($basename, 'locallang') !== 0) {
            $result = 'ERROR: Filename didn\'t start with "locallang".';
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
            /** @noinspection PhpIncludeInspection */
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
        if (substr($fileRef, -4) === '.xml' || substr($fileRef, -4) === '.php') {
            $lang = $lang === 'default' ? '' : $lang . '.';
            $path = $lang . pathinfo($fileRef, PATHINFO_FILENAME) . '.xlf';
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
        $xml = $this->generateFileContent($xmlFile, $langKey);

        $result = '';
        if (!file_exists($newFileName)) {
            GeneralUtility::writeFile($newFileName, $xml);

            $result = $newFileName;
        }
        return $result;
    }

    /**
     * @param string $xmlFile
     * @param string $langKey
     *
     * @return string
     */
    protected function generateFileContent(string $xmlFile, string $langKey): string
    {
        // Initialize variables:
        $xml = [];
        $LOCAL_LANG = $this->getCombinedTranslationFileContent($xmlFile);

        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<xliff version="1.0" xmlns:t3="http://typo3.org/schemas/xliff">';
        $xml[] = '	<file t3:id="' . time() . '" source-language="en"'
            . ($langKey !== 'default' ? ' target-language="' . $langKey . '"' : '')
            . ' datatype="plaintext" original="messages" date="'
            . gmdate('Y-m-d\TH:i:s\Z') . '"' . ' product-name="' . $this->extension . '">';
        $xml[] = '		<header/>';
        $xml[] = '		<body>';

        foreach ($LOCAL_LANG[$langKey] as $key => $data) {
            if (is_array($data)) {
                $source = $data[0]['source'];
                $target = $data[0]['target'];
            } else {
                $source = $LOCAL_LANG['default'][$key];
                $target = $data;
            }

            if (strpos($source, chr(10)) !== false || strpos($source, chr(10)) !== false) {
                $preserve = 'xml:space="preserve"';
            } else {
                $preserve = '';
            }

            if (empty($source)) {
                $source = '<source/>';
            } else {
                $source = '<source>' . htmlspecialchars($source) . '</source>';
            }

            if (empty($target)) {
                $target = '<target/>';
            } else {
                $target = '<target>' . htmlspecialchars($target) . '</target>';
            }

            if ($langKey === 'default') {
                $xml[] = '			<trans-unit id="' . $key . '" resname="' . $key . '" ' . $preserve . '>';
                $xml[] = '				' . $source;
            } else {
                $xml[] = '			<trans-unit id="' . $key . '" resname="' . $key . '" ' . $preserve
                    . ' approved="yes">';
                $xml[] = '				' . $source;
                $xml[] = '				' . $target;
            }
            $xml[] = '			</trans-unit>';
        }

        $xml[] = '		</body>';
        $xml[] = '	</file>';
        $xml[] = '</xliff>';

        return implode(LF, $xml);
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
        if (strpos($languageFile, '.xml')) {
            $ll = GeneralUtility::xml2array(file_get_contents($languageFile));
            $includedLanguages = array_keys($ll['data']);

            $LOCAL_LANG = [];
            foreach ($includedLanguages as $langKey) {
                /** @var $parser \Evoweb\EwLlxml2xliff\Localization\Parser\LocallangXmlParser */
                $parser = GeneralUtility::makeInstance(
                    \Evoweb\EwLlxml2xliff\Localization\Parser\LocallangXmlParser::class
                );
                $localLangContent = $parser->getParsedData($languageFile, $langKey);
                unset($parser);
                $LOCAL_LANG[$langKey] = $localLangContent[$langKey];
            }
        } else {
            /** @noinspection PhpIncludeInspection */
            require($languageFile);
            $includedLanguages = isset($LOCAL_LANG) ? array_keys($LOCAL_LANG) : [];
        }

        if (empty($includedLanguages)) {
            throw new \RuntimeException('data section not found in "' . $languageFile . '"', 1314187884);
        }

        /** @noinspection PhpUndefinedVariableInspection */
        return $LOCAL_LANG;
    }

    /**
     * Converts an XML string to a PHP array.
     * This is the reverse function of array2xml()
     * This is a wrapper for xml2arrayProcess that adds a two-level cache
     *
     * @param string $string XML content to convert into an array
     * @param string $NSprefix The tag-prefix resolve, eg. a namespace like "T3:"
     * @param bool $reportDocTag If set, the document tag will be set in the key "_DOCUMENT_TAG" of the output array
     *
     * @return mixed If the parsing had errors, a string with the error message is returned.
     *  Otherwise an array with the content.
     *
     * @see array2xml(),xml2arrayProcess()
     */
    protected function xml2array(string $string, string $NSprefix = '', bool $reportDocTag = false)
    {
        return GeneralUtility::xml2array($string, $NSprefix, $reportDocTag);
    }
}
