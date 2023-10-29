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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\Controller;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;

#[Controller]
class FileController
{
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly PageRenderer $pageRenderer,
        protected readonly Converter $fileConverter,
        protected readonly ExtensionService $extensionService,
        protected readonly IconFactory $iconFactory,
    ) {
    }

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            [$extensions] = $this->prepareExtensions($request, false);
        } catch (\Exception) {
            $extensions = [];
        }

        $view = $this->initializeModuleTemplate($request);

        $view->assign('extensions', $extensions);

        return $view->renderResponse('File/Index');
    }

    /**
     * @throws ImmediateResponseException
     */
    public function showFilesAction(ServerRequestInterface $request): ResponseInterface
    {
        [$extensions, $selectedExtension] = $this->prepareExtensions($request);
        try {
            [$files] = $this->prepareFiles($request, $selectedExtension, false);
        } catch (\Exception) {
            $files = [];
        }

        $view = $this->initializeModuleTemplate($request);

        $view->assignMultiple([
            'extensions' => $extensions,
            'selectedExtension' => $selectedExtension,
            'files' => $files,
        ]);

        return $view->renderResponse('File/ShowFiles');
    }

    /**
     * @throws ImmediateResponseException
     */
    public function confirmConversionAction(ServerRequestInterface $request): ResponseInterface
    {
        [$extensions, $selectedExtension] = $this->prepareExtensions($request);
        [$files, $selectedFile] = $this->prepareFiles($request, $selectedExtension);

        $view = $this->initializeModuleTemplate($request);

        $view->assignMultiple([
            'extensions' => $extensions,
            'selectedExtension' => $selectedExtension,
            'files' => $files,
            'selectedFile' => $selectedFile,
        ]);

        return $view->renderResponse('File/ConfirmConversion');
    }

    /**
     * @throws ImmediateResponseException
     */
    public function convertFileAction(ServerRequestInterface $request): ResponseInterface
    {
        [$extensions, $selectedExtension] = $this->prepareExtensions($request);
        [$files, $selectedFile] = $this->prepareFiles($request, $selectedExtension);

        $conversionResult = $this->extensionService->convertLanguageFile($selectedExtension, $selectedFile, $files);

        $view = $this->initializeModuleTemplate($request);

        $view->assignMultiple([
            'extensions' => $extensions,
            'selectedExtension' => $selectedExtension,
            'files' => $files,
            'selectedFile' => $selectedFile,
            ...$conversionResult,
        ]);

        return $view->renderResponse('File/ConvertFile');
    }

    /**
     * @throws ImmediateResponseException
     */
    protected function prepareExtensions(ServerRequestInterface $request, bool $selected = true): array
    {
        $extensions = $this->extensionService->getLocalExtensions();
        $selectedExtension = $selected ? $this->getSelectedExtension($request, $extensions) : '';

        foreach ($extensions as &$extension) {
            $extension['selected'] = $extension['key'] === $selectedExtension ? ' selected="selected"' : '';
        }

        return [$extensions, $selectedExtension];
    }

    /**
     * @throws ImmediateResponseException
     */
    protected function prepareFiles(ServerRequestInterface $request, string $extension, bool $selected = true): array
    {
        $files = $this->extensionService->getFilesOfExtension($extension);
        $selectedFile = $selected ? $this->getSelectedFile($request, $files) : '';

        foreach ($files as &$file) {
            $file['selected'] = $file['filename'] === $selectedFile ? ' selected="selected"' : '';
        }

        return [$files, $selectedFile];
    }

    /**
     * @throws ImmediateResponseException
     */
    protected function getSelectedExtension(ServerRequestInterface $request, array $extensions): string
    {
        $selectedExtension = $this->isArgumentSetAndAvailable($request, $extensions, 'extension');
        if (!$selectedExtension) {
            throw new ImmediateResponseException($this->indexAction($request));
        }
        return $selectedExtension;
    }

    /**
     * @throws ImmediateResponseException
     */
    protected function getSelectedFile(ServerRequestInterface $request, array $files): string
    {
        $selectedFile = $this->isArgumentSetAndAvailable($request, $files, 'file');
        if (!$selectedFile) {
            throw new ImmediateResponseException($this->showFilesAction($request));
        }
        return $selectedFile;
    }

    protected function isArgumentSetAndAvailable(ServerRequestInterface $request, array $values, string $key): ?string
    {
        $formFieldValues = $request->getParsedBody() ?? [];
        $formFieldValue = $formFieldValues[$key] ?? '';
        return empty($values) || empty($formFieldValue) || !isset($values[$formFieldValue]) ? null : $formFieldValue;
    }

    protected function initializeModuleTemplate(ServerRequestInterface $request): ModuleTemplate
    {
        $this->pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction(
            JavaScriptModuleInstruction::create('@evoweb/ew-llxml2xliff/form.js')
        );
        $this->pageRenderer->addCssFile('EXT:ew_llxml2xliff/Resources/Public/Css/form.css');

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle(
            $this->getLanguageService()
                ->sL('LLL:EXT:ew_llxml2xliff/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab')
        );
        return $moduleTemplate;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
