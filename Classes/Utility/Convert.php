<?php
namespace Evoweb\EwLlxml2xliff\Utility;

/***************************************************************
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
***************************************************************/

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Conversion of locallang.xml files to locallang.xlf.
 *
 * @author Xavier Perseguers <xavier@typo3.org>
 */
class Convert
{
    /** @var string */
    protected $extension;

    /**
     * Main function.
     *
     * @param string $xmlFile Absolute path to the selected ll-XML file
     * @param string $extension Extension key to get extension path
     * @return string HTML content
     */
    public function writeXlfFilesInPlace($xmlFile, $extension)
    {
        $this->extension = $extension;
        $xmlFile = ExtensionManagementUtility::extPath($extension) . $xmlFile;

        if (@is_file($xmlFile)) {
            $fileCheckResult = $this->checkXmlFilename($xmlFile);
            if (empty($fileCheckResult)) {
                $languages = $this->getAvailableTranslations($xmlFile);
                $errors = array();
                foreach ($languages as $langKey) {
                    $newFileName = dirname($xmlFile) . '/' . $this->localizedFileRef($xmlFile, $langKey);
                    if (@is_file($newFileName)) {
                        $errors[] = 'ERROR: Output file "' . $newFileName . '" already exists!';
                    }
                }
                if (empty($errors)) {
                    $output = '';
                    foreach ($languages as $langKey) {
                        $newFileName = dirname($xmlFile) . '/' . $this->localizedFileRef($xmlFile, $langKey);
                        $output .= $this->writeNewXliffFile($xmlFile, $newFileName, $langKey) . '<br />';
                    }
                    return $output;
                } else {
                    return implode('<br />', $errors);
                }
            } else {
                return $fileCheckResult;
            }
        }
        return 'File ' . $xmlFile . ' does not exists!';
    }

    /**
     * Main function.
     *
     * @param string $xmlFile Absolute path to the uploaded ll-XML file
     * @return string HTML content
     */
    public function convertAndDownload($xmlFile)
    {
        $result = '';
        if (@is_file($xmlFile)) {
            $fileCheckResult = $this->checkXmlFilename($xmlFile);
            if (empty($fileCheckResult)) {
                $languages = $this->getAvailableTranslations($xmlFile);
                if (!empty($languages)) {
                    $zip = new \ZipStream\ZipStream('translated_' . basename($xmlFile) . '.zip');
                    $fileHandle = tmpfile();
                    foreach ($languages as $langKey) {
                        ftruncate($fileHandle, 0);
                        fwrite($fileHandle, $this->generateFileContent($xmlFile, $langKey));
                        $zip->addFileFromStream($this->localizedFileRef($xmlFile, $langKey), $fileHandle);
                    }
                    fclose($fileHandle);
                    unlink($xmlFile);
                    $zip->finish();
                } else {
                    $result = 'ERROR: no translation files could be created!';
                }
            }
            unlink($xmlFile);
        } else {
            $result = 'File ' . $xmlFile . ' does not exists!';
        }
        return $result;
    }

    /**
     * Checking for a valid locallang*.xml filename.
     *
     * @param string $xmlFile Absolute reference to the ll-XML locallang file
     * @return string Empty (false) return value means "OK" while otherwise is an error string
     */
    protected function checkXmlFilename($xmlFile)
    {
        $basename = basename($xmlFile);

        $result = '';
        if (strpos($basename, 'locallang') !== 0) {
            $result = 'ERROR: Filename didn\'t start with "locallang".';
        }
        return $result;
    }

    /**
     * @param string $xmlFile Absolute reference to the ll-XML base locallang file
     * @return array
     */
    protected function getAvailableTranslations($xmlFile)
    {
        $ll = $this->xml2array(file_get_contents($xmlFile));
        if (!isset($ll['data'])) {
            throw new \RuntimeException('data section not found in "' . $xmlFile . '"', 1314187884);
        }

        return array_keys($ll['data']);
    }

    /**
     * Returns localized fileRef ([langkey].locallang*.xml)
     *
     * @param string $fileRef Filename/path of a 'locallang*.xml' file
     * @param string $lang Language key
     * @return string Input filename with a '[lang-key].locallang*.xml' name if $this->lang is not 'default'
     */
    protected function localizedFileRef($fileRef, $lang)
    {
        $path = '';
        if (substr($fileRef, -4) === '.xml') {
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
     * @return string HTML text string message
     */
    protected function writeNewXliffFile($xmlFile, $newFileName, $langKey)
    {
        $xml = $this->generateFileContent($xmlFile, $langKey);

        if (!file_exists($newFileName)) {
            GeneralUtility::writeFile($newFileName, $xml);

            return $newFileName;
        }
        return '';
    }

    /**
     * @param string $xmlFile
     * @param string $langKey
     * @return string
     */
    protected function generateFileContent($xmlFile, $langKey)
    {
        // Initialize variables:
        $xml = [];
        $LOCAL_LANG = $this->getLLarray($xmlFile);

        $xml[] = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>';
        $xml[] = '<xliff version="1.0">';
        $xml[] = '	<file source-language="en"'
            . ($langKey !== 'default' ? ' target-language="' . $langKey . '"' : '')
            . ' datatype="plaintext" original="messages" date="'
            . gmdate('Y-m-d\TH:i:s\Z') . '"' . ' product-name="' . $this->extension . '">';
        $xml[] = '		<header/>';
        $xml[] = '		<body>';

        foreach ($LOCAL_LANG[$langKey] as $key => $data) {
            $source = $data[0]['source'];
            $target = $data[0]['target'];

            if ($langKey === 'default') {
                $xml[] = '			<trans-unit id="' . $key . '" xml:space="preserve">';
                $xml[] = '				<source>' . htmlspecialchars($source) . '</source>';
                $xml[] = '			</trans-unit>';
            } else {
                $xml[] = '			<trans-unit id="' . $key . '" xml:space="preserve" approved="yes">';
                $xml[] = '				<source>' . htmlspecialchars($source) . '</source>';
                $xml[] = '				<target>' . htmlspecialchars($target) . '</target>';
                $xml[] = '			</trans-unit>';
            }
        }

        $xml[] = '		</body>';
        $xml[] = '	</file>';
        $xml[] = '</xliff>';

        return implode(LF, $xml);
    }

    /**
     * Includes locallang files and returns raw $LOCAL_LANG array
     *
     * @param string $xmlFile Absolute reference to the ll-XML locallang file.
     * @return array LOCAL_LANG array from ll-XML file (with all possible sub-files for languages included)
     */
    protected function getLLarray($xmlFile)
    {
        $ll = GeneralUtility::xml2array(file_get_contents($xmlFile));
        if (!isset($ll['data'])) {
            throw new \RuntimeException('data section not found in "' . $xmlFile . '"', 1314187884);
        }
        $includedLanguages = array_keys($ll['data']);
        $LOCAL_LANG = array();

        foreach ($includedLanguages as $langKey) {
            /** @var $parser \TYPO3\CMS\Core\Localization\Parser\LocallangXmlParser */
            $parser = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Localization\Parser\LocallangXmlParser::class);
            $llang = $parser->getParsedData($xmlFile, $langKey, $GLOBALS['LANG']->charSet);
            unset($parser);
            $LOCAL_LANG[$langKey] = $llang[$langKey];
        }

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
     * @return mixed If the parsing had errors, a string with the error message is returned.
     *  Otherwise an array with the content.
     * @see array2xml(),xml2arrayProcess()
     */
    protected function xml2array($string, $NSprefix = '', $reportDocTag = false)
    {
        return GeneralUtility::xml2array($string, $NSprefix, $reportDocTag);
    }
}
