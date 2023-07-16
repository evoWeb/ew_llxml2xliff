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

use Evoweb\EwLlxml2xliff\File\Convert;
use Evoweb\EwLlxml2xliff\Service\ExtensionService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class FileController extends ActionController
{
    protected Convert $convertUtility;

    protected ExtensionService $extensionService;

    public function __construct(
        Convert $fileConverter,
        ExtensionService $extensionService
    ) {
        $this->convertUtility = $fileConverter;
        $this->extensionService = $extensionService;
    }

    public function indexAction(): ResponseInterface
    {
        $extensions = $this->extensionService->getLocalExtensions();
        $this->view->assign('extensions', $extensions);

        return new HtmlResponse($this->view->render());
    }

    public function showFilesAction(): ResponseInterface
    {
        $extensions = $this->extensionService->getLocalExtensions();
        $selectedExtension = $this->isArgumentSetAndAvailable($extensions, 'extension');

        if ($selectedExtension) {
            $files = $this->extensionService->getFilesOfExtension($selectedExtension);

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
        $extensions = $this->extensionService->getLocalExtensions();
        $selectedExtension = $this->isArgumentSetAndAvailable($extensions, 'extension');

        if ($selectedExtension) {
            $files = $this->extensionService->getFilesOfExtension($selectedExtension);
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
        $extensions = $this->extensionService->getLocalExtensions();
        $selectedExtension = $this->isArgumentSetAndAvailable($extensions, 'extension');

        if ($selectedExtension) {
            $files = $this->extensionService->getFilesOfExtension($selectedExtension);
            $selectedFile = $this->isArgumentSetAndAvailable($files, 'file');

            if ($selectedFile) {
                $extensionPath = ExtensionManagementUtility::extPath($selectedExtension);
                if ($this->extensionService->xliffFileAlreadyExists($extensionPath, $selectedFile)) {
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

    protected function isArgumentSetAndAvailable(array $values, string $key): ?string
    {
        $value = $this->request->hasArgument($key) ? $this->request->getArgument($key) : '';
        return empty($values) || empty($value) || !isset($values[$value]) ? false : $value;
    }
}
