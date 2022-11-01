<?php

namespace Evoweb\EwLlxml2xliff\Controller;

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

use Evoweb\EwLlxml2xliff\Utility\Convert;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extensionmanager\Utility\ListUtility;

class FileController extends ActionController
{
    protected ListUtility $listUtility;

    protected Convert $convertUtility;

    public function __construct(
        ListUtility $listUtility,
        Convert $convertUtility
    ) {
        $this->listUtility = $listUtility;
        $this->convertUtility = $convertUtility;
    }

    public function indexAction(): ResponseInterface
    {
        $extensions = $this->getLocalExtensions();
        $extensionsWithFileToConvert = [];
        foreach ($extensions as $extension) {
            if (isset($extension['type'])) {
                $extensionsWithFileToConvert[] = $extension;
            }
        }
        $this->view->assign('extensions', $extensionsWithFileToConvert);

        return new HtmlResponse($this->view->render());
    }

    public function showFilesAction(): ResponseInterface
    {
        $extensions = $this->getLocalExtensions();
        $selectedExtension = $this->isArgumentSetAndAvailable($extensions, 'extension');

        if ($selectedExtension) {
            $files = $this->getFilesOfExtension($selectedExtension);

            $this->view->assign('extensions', $extensions);
            $this->view->assign('selectedExtension', $selectedExtension);
            $this->view->assign('files', $files);
            $response = new HtmlResponse($this->view->render());
        } else {
            $response = new ForwardResponse('index');
        }

        return $response;
    }

    public function confirmConversionAction(): ResponseInterface
    {
        $extensions = $this->getLocalExtensions();
        $selectedExtension = $this->isArgumentSetAndAvailable($extensions, 'extension');

        if ($selectedExtension) {
            $files = $this->getFilesOfExtension($selectedExtension);
            $selectedFile = $this->isArgumentSetAndAvailable($files, 'file');

            if ($selectedFile) {
                $this->view->assign('extensions', $extensions);
                $this->view->assign('selectedExtension', $selectedExtension);
                $this->view->assign('files', $files);
                $this->view->assign('selectedFile', $selectedFile);
                $response = new HtmlResponse($this->view->render());
            } else {
                $response = new ForwardResponse('showFiles');
            }
        } else {
            $response = new ForwardResponse('index');
        }

        return $response;
    }

    public function convertFileAction(): ResponseInterface
    {
        $extensions = $this->getLocalExtensions();
        $selectedExtension = $this->isArgumentSetAndAvailable($extensions, 'extension');

        if ($selectedExtension) {
            $files = $this->getFilesOfExtension($selectedExtension);
            $selectedFile = $this->isArgumentSetAndAvailable($files, 'file');

            if ($selectedFile) {
                $extensionPath = ExtensionManagementUtility::extPath($selectedExtension);
                if ($this->xliffFileAlreadyExists($extensionPath, $selectedFile)) {
                    $this->view->assign('wasConvertedPreviously', 1);
                } else {
                    $this->convertUtility->setExtension($selectedExtension);
                    if (strpos($selectedFile, '.xml') !== false) {
                        $messages = $this->convertUtility->writeXmlAsXlfFilesInPlace($selectedFile);
                    } else {
                        $messages = $this->convertUtility->writePhpAsXlfFilesInPlace($selectedFile);
                    }

                    if (strpos($messages, 'ERROR') === false) {
                        $this->view->assign('fileConvertedSuccessfully', 1);
                    }
                    $this->view->assign('messages', $messages);
                    unset($files[$selectedFile]);
                }

                $this->view->assign('extensions', $extensions);
                $this->view->assign('selectedExtension', $selectedExtension);
                $this->view->assign('files', $files);
                $this->view->assign('selectedFile', '');
                $this->view->assign('convertedFile', $selectedFile);
                $response = new HtmlResponse($this->view->render());
            } else {
                $response = new ForwardResponse('showFiles');
            }
        } else {
            $response = new ForwardResponse('index');
        }

        return $response;
    }

    protected function getLocalExtensions(): array
    {
        $availableExtensions = $this->listUtility->getAvailableExtensions();
        $extensions = array_filter($availableExtensions, function ($extension, $key) {
            /** @var array $extension */
            /** @var string $key */
            return $extension['type'] == 'Local' && ExtensionManagementUtility::isLoaded($key);
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
    protected function getFilesOfExtension(string $extensionKey): array
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
        return strpos($filePath, 'locallang') !== false;
    }

    protected function xliffFileAlreadyExists(string $extensionPath, string $filePath): bool
    {
        $xliffFileName = preg_replace('#\.(xml|php)$#', '.xlf', $extensionPath . $filePath);

        return (bool)file_exists($xliffFileName);
    }

    protected function isArgumentSetAndAvailable(array $values, string $key): ?string
    {
        $value = $this->request->hasArgument($key) ? $this->request->getArgument($key) : '';
        return empty($values) || empty($value) || !isset($values[$value]) ? false : $value;
    }
}
