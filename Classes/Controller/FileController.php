<?php

namespace Evoweb\EwLlxml2xliff\Controller;

use Evoweb\EwLlxml2xliff\Utility\Convert as Convert;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extensionmanager\Utility\ListUtility as ListUtility;

class FileController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * @var ListUtility
     */
    protected $listUtility;

    /**
     * @var Convert
     */
    protected $convertUtility;

    public function __construct(
        ListUtility $listUtility,
        Convert $convertUtility
    ) {
        $this->listUtility = $listUtility;
        $this->convertUtility = $convertUtility;
    }

    public function indexAction()
    {
        $extensions = $this->getLocalExtensions();
        $extensionsWithFileToConvert = [];
        foreach ($extensions as $extension) {
            if (isset($extension['type']) && $this->getFilesOfExtension($extension['key'])) {
                $extensionsWithFileToConvert[] = $extension;
            }
        }
        $this->view->assign('extensions', $extensionsWithFileToConvert);
    }

    public function showFilesAction()
    {
        $extensions = $this->getLocalExtensions();
        $selectedExtension = $this->isArgumentSetAndAvailable($extensions, 'extension', 'index');

        $files = $this->getFilesOfExtension($selectedExtension);

        $this->view->assign('extensions', $extensions);
        $this->view->assign('selectedExtension', $selectedExtension);
        $this->view->assign('files', $files);
    }

    public function confirmConversionAction()
    {
        $extensions = $this->getLocalExtensions();
        $selectedExtension = $this->isArgumentSetAndAvailable($extensions, 'extension', 'index');

        $files = $this->getFilesOfExtension($selectedExtension);
        $selectedFile = $this->isArgumentSetAndAvailable($files, 'file', 'showFiles');

        $this->view->assign('extensions', $extensions);
        $this->view->assign('selectedExtension', $selectedExtension);
        $this->view->assign('files', $files);
        $this->view->assign('selectedFile', $selectedFile);
    }

    public function convertFileAction()
    {
        $extensions = $this->getLocalExtensions();
        $selectedExtension = $this->isArgumentSetAndAvailable($extensions, 'extension', 'index');

        $files = $this->getFilesOfExtension($selectedExtension);
        $selectedFile = $this->isArgumentSetAndAvailable($files, 'file', 'showFiles');

        $extensionPath = ExtensionManagementUtility::extPath($selectedExtension);
        if ($this->xliffFileAlreadyExists($extensionPath, $selectedFile)) {
            $this->view->assign('wasConvertedPreviously', 1);
        } else {
            if (strpos($selectedFile, '.xml') !== false) {
                $messages = $this->convertUtility->writeXmlAsXlfFilesInPlace($selectedFile, $selectedExtension);
            } else {
                $messages = $this->convertUtility->writePhpAsXlfFilesInPlace($selectedFile, $selectedExtension);
            }

            if (strpos($messages, 'ERROR') === false) {
                $this->view->assign('fileConvertedSuccessfully', 1);
                $this->view->assign('messages', $messages);
            }
            unset($files[$selectedFile]);
        }

        $this->view->assign('extensions', $extensions);
        $this->view->assign('selectedExtension', $selectedExtension);
        $this->view->assign('files', $files);
        $this->view->assign('selectedFile', '');
        $this->view->assign('convertedFile', $selectedFile);
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

        return (bool) file_exists($xliffFileName);
    }

    protected function isArgumentSetAndAvailable(array $values, string $key, string $action): string
    {
        $value = $this->request->hasArgument($key) ? $this->request->getArgument($key) : '';
        if (empty($values) || !isset($values[$value])) {
            $this->forward($action);
        }
        return $value;
    }
}
