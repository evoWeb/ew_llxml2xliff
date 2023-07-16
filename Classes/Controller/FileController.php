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

namespace Evoweb\EwLlxml2xliff\Controller;

use Evoweb\EwLlxml2xliff\File\Converter;
use Evoweb\EwLlxml2xliff\Service\ExtensionService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\Controller;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;

#[Controller]
class FileController
{
    public function __construct(
        protected IconFactory $iconFactory,
        protected UriBuilder $uriBuilder,
        protected ModuleTemplateFactory $moduleTemplateFactory,
        protected ResponseFactoryInterface $responseFactory,
        protected Converter $fileConverter,
        protected ExtensionService $extensionService
    ) {
    }

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle(
            $this->getLanguageService()->sL(
                'LLL:EXT:ew_llxml2xliff/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab'
            )
        );

        $extensions = $this->extensionService->getLocalExtensions();
        $view->assign('extensions', $extensions);

        return $view->renderResponse('File/Index');
    }

    public function showFilesAction(ServerRequestInterface $request): ResponseInterface
    {
        $extensions = $this->extensionService->getLocalExtensions();
        $selectedExtension = $this->isArgumentSetAndAvailable($request, $extensions, 'extension');

        if (!$selectedExtension) {
            $response = new ForwardResponse('index');
        } else {
            $view = $this->moduleTemplateFactory->create($request);
            $view->setTitle(
                $this->getLanguageService()->sL(
                    'LLL:EXT:ew_llxml2xliff/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab'
                )
            );

            $files = $this->extensionService->getFilesOfExtension($selectedExtension);

            $view->assign('extensions', $extensions);
            $view->assign('selectedExtension', $selectedExtension);
            $view->assign('files', $files);

            $response = $view->renderResponse('File/ShowFiles');
        }

        return $response;
    }

    public function confirmConversionAction(ServerRequestInterface $request): ResponseInterface
    {
        $extensions = $this->extensionService->getLocalExtensions();
        $selectedExtension = $this->isArgumentSetAndAvailable($request, $extensions, 'extension');

        if (!$selectedExtension) {
            $response = new ForwardResponse('index');
        } else {
            $files = $this->extensionService->getFilesOfExtension($selectedExtension);
            $selectedFile = $this->isArgumentSetAndAvailable($request, $files, 'file');

            if (!$selectedFile) {
                $response = new ForwardResponse('showFiles');
            } else {
                $view = $this->moduleTemplateFactory->create($request);
                $view->setTitle(
                    $this->getLanguageService()->sL(
                        'LLL:EXT:ew_llxml2xliff/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab'
                    )
                );

                $view->assign('extensions', $extensions);
                $view->assign('selectedExtension', $selectedExtension);
                $view->assign('files', $files);
                $view->assign('selectedFile', $selectedFile);
                $response = $view->renderResponse('File/ConfirmConversion');
            }
        }

        return $response;
    }

    public function convertFileAction(ServerRequestInterface $request): ResponseInterface
    {
        $extensions = $this->extensionService->getLocalExtensions();
        $selectedExtension = $this->isArgumentSetAndAvailable($request, $extensions, 'extension');

        if (!$selectedExtension) {
            $response = new ForwardResponse('index');
        } else {
            $files = $this->extensionService->getFilesOfExtension($selectedExtension);
            $selectedFile = $this->isArgumentSetAndAvailable($request, $files, 'file');

            if (!$selectedFile) {
                $response = new ForwardResponse('showFiles');
            } else {
                $view = $this->moduleTemplateFactory->create($request);
                $view->setTitle(
                    $this->getLanguageService()->sL(
                        'LLL:EXT:ew_llxml2xliff/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab'
                    )
                );

                $extensionPath = ExtensionManagementUtility::extPath($selectedExtension);
                if ($this->extensionService->xliffFileAlreadyExists($extensionPath, $selectedFile)) {
                    $view->assign('wasConvertedPreviously', 1);
                } else {
                    $this->fileConverter->setExtension($selectedExtension);
                    if (str_contains($selectedFile, '.xml')) {
                        $messages = $this->fileConverter->writeXmlAsXlfFilesInPlace($selectedFile);
                    } else {
                        $messages = $this->fileConverter->writePhpAsXlfFilesInPlace($selectedFile);
                    }

                    if (!str_contains($messages, 'ERROR')) {
                        $view->assign('fileConvertedSuccessfully', 1);
                    }
                    $view->assign('messages', $messages);
                    unset($files[$selectedFile]);
                }

                $view->assign('extensions', $extensions);
                $view->assign('selectedExtension', $selectedExtension);
                $view->assign('files', $files);
                $view->assign('selectedFile', '');
                $view->assign('convertedFile', $selectedFile);
                $response = $view->renderResponse('File/ConvertFile');
            }
        }

        return $response;
    }

    protected function isArgumentSetAndAvailable(ServerRequestInterface $request, array $values, string $key): ?string
    {
        $formFieldValues = $request->getParsedBody() ?? [];
        $formFieldValue = $formFieldValues[$key] ?? '';
        return empty($values) || empty($formFieldValue) || !isset($values[$formFieldValue]) ? null : $formFieldValue;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
