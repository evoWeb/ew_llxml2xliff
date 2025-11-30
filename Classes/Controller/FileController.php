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

namespace Evoweb\EwLlxml2xliff\Controller;

use Evoweb\EwLlxml2xliff\File\Converter;
use Evoweb\EwLlxml2xliff\Service\ExtensionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsController]
readonly class FileController
{
    public function __construct(
        protected IconFactory $iconFactory,
        protected UriBuilder $uriBuilder,
        protected PageRenderer $pageRenderer,
        protected ModuleTemplateFactory $moduleTemplateFactory,
        protected Converter $fileConverter,
        protected ExtensionService $extensionService,
        protected ComponentFactory $componentFactory,
    ) {}

    public function selectExtensionAction(ServerRequestInterface $request): ResponseInterface
    {
        [$extensions] = $this->prepareExtensions($request, false);

        $moduleTemplate = $this->initializeModuleTemplate($request, 'ew_llxml2xliff.messages:extension');
        $moduleTemplate->assign('extensions', $extensions);
        return $moduleTemplate->renderResponse('File/SelectExtension');
    }

    public function selectFileAction(ServerRequestInterface $request): ResponseInterface
    {
        [$extensions, $selectedExtension, $selectedExtensionKey] = $this->prepareExtensions($request);
        [$files] = $this->prepareFiles($request, $selectedExtensionKey, false);

        $moduleTemplate = $this->initializeModuleTemplate($request, 'ew_llxml2xliff.messages:file');
        $moduleTemplate->assignMultiple([
            'extensions' => $extensions,
            'selectedExtension' => $selectedExtension,
            'selectedExtensionKey' => $selectedExtensionKey,
            'files' => $files,
        ]);
        return $moduleTemplate->renderResponse('File/SelectFile');
    }

    public function confirmConversionAction(ServerRequestInterface $request): ResponseInterface
    {
        [$extensions, $selectedExtension, $selectedExtensionKey] = $this->prepareExtensions($request);
        [$files, $selectedFile, $selectedFileKey] = $this->prepareFiles($request, $selectedExtensionKey);

        $moduleTemplate = $this->initializeModuleTemplate($request, 'ew_llxml2xliff.messages:confirm_selection');
        $moduleTemplate->assignMultiple([
            'extensions' => $extensions,
            'selectedExtension' => $selectedExtension,
            'selectedExtensionKey' => $selectedExtensionKey,
            'files' => $files,
            'selectedFile' => $selectedFile,
            'selectedFileKey' => $selectedFileKey,
        ]);
        return $moduleTemplate->renderResponse('File/ConfirmConversion');
    }

    public function convertFileAction(ServerRequestInterface $request): ResponseInterface
    {
        [$extensions, $selectedExtension, $selectedExtensionKey] = $this->prepareExtensions($request);
        [$files, $selectedFile, $selectedFileKey] = $this->prepareFiles($request, $selectedExtensionKey);

        $conversionResult = $this->extensionService
            ->convertLanguageFile($selectedExtensionKey, $selectedFileKey, $files);

        $moduleTemplate = $this->initializeModuleTemplate($request, 'ew_llxml2xliff.messages:finish');
        $moduleTemplate->assignMultiple([
            'extensions' => $extensions,
            'selectedExtension' => $selectedExtension,
            'selectedExtensionKey' => $selectedExtensionKey,
            'files' => $files,
            'selectedFile' => $selectedFile,
            'selectedFileKey' => $selectedFileKey,
            ...$conversionResult,
        ]);
        return $moduleTemplate->renderResponse('File/ConvertFile');
    }

    /**
     * @return array<string|array<array<string, string>>>
     */
    protected function prepareExtensions(ServerRequestInterface $request, bool $selected = true): array
    {
        $extensions = $this->extensionService->getLocalExtensions();
        $selectedExtensionKey = $selected ? $this->getSelectedExtension($request, $extensions) : '';
        $selectedExtension = null;

        foreach ($extensions as &$extension) {
            if ($extension['key'] === $selectedExtensionKey) {
                $extension['selected'] = ' selected="selected"';
                $selectedExtension = $extension;
            } else {
                $extension['selected'] = '';
            }
        }

        return [$extensions, $selectedExtension, $selectedExtensionKey];
    }

    /**
     * @return array<string|array<array<string, string>>>
     */
    protected function prepareFiles(ServerRequestInterface $request, string $extension, bool $selected = true): array
    {
        $files = $this->extensionService->getFilesOfExtension($extension);
        $selectedFileKey = $selected ? $this->getSelectedFile($request, $files) : '';
        $selectedFile = null;

        foreach ($files as &$file) {
            if ($file['filename'] === $selectedFileKey) {
                $file['selected'] = ' selected="selected"';
                $selectedFile = $file;
            } else {
                $file['selected'] = '';
            }
        }

        return [$files, $selectedFile, $selectedFileKey];
    }

    /**
     * @param array<string, mixed> $extensions
     */
    protected function getSelectedExtension(ServerRequestInterface $request, array $extensions): string
    {
        $selectedExtension = $this->isArgumentSetAndAvailable($request, $extensions, 'extension');
        if ($selectedExtension === '') {
            throw new PropagateResponseException($this->selectExtensionAction($request));
        }
        return $selectedExtension;
    }

    /**
     * @param array<string, mixed> $files
     */
    protected function getSelectedFile(ServerRequestInterface $request, array $files): string
    {
        $selectedFile = $this->isArgumentSetAndAvailable($request, $files, 'file');
        if ($selectedFile === '') {
            throw new PropagateResponseException($this->selectFileAction($request));
        }
        return $selectedFile;
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function isArgumentSetAndAvailable(ServerRequestInterface $request, array $values, string $key): string
    {
        $moduleData = $request->getAttribute('moduleData');
        $moduleData->cleanUp(['extension', 'file']);
        $formFieldValue = $moduleData->get($key);
        return ($formFieldValue !== '' && isset($values[$formFieldValue])) ? $formFieldValue : '';
    }

    protected function initializeModuleTemplate(ServerRequestInterface $request, string $context): ModuleTemplate
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle(
            $this->getLanguageService()->sL('ew_llxml2xliff.mod:title'),
            $this->getLanguageService()->sL($context)
        );

        try {
            $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
            $newFileConversionUrl = (string)$this->uriBuilder->buildUriFromRoute('web_EwLlxml2xliff');
            $newFileConversionButton = $this->componentFactory->createLinkButton()
                ->setDataAttributes(['identifier' => 'newFileConversion'])
                ->setHref($newFileConversionUrl)
                ->setTitle(
                    $this->getLanguageService()->sL(
                        'ew_llxml2xliff.messages:start_new_conversion'
                    )
                )
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-plus', IconSize::SMALL));
            $buttonBar->addButton($newFileConversionButton);
        } catch (\Exception) {
        }

        return $moduleTemplate;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
