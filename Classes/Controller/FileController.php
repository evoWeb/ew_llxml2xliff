<?php
namespace Evoweb\EwLlxml2xliff\Controller;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * @var \TYPO3\CMS\Extensionmanager\Utility\ListUtility
     */
    protected $listUtility;

    /**
     * @param \TYPO3\CMS\Extensionmanager\Utility\ListUtility $listUtility
     */
    public function injectListUtility(\TYPO3\CMS\Extensionmanager\Utility\ListUtility $listUtility)
    {
        $this->listUtility = $listUtility;
    }


    /**
     * @return void
     */
    public function indexAction()
    {
        $this->view->assign('extensions', $this->getLocalExtensions());
    }

    /**
     * @return void
     */
    public function showFilesAction()
    {
        $extensions = $this->getLocalExtensions();
        $selectedExtension = $this->request->hasArgument('extension') ? $this->request->getArgument('extension') : '';
        if (empty($selectedExtension) || !isset($extensions[$selectedExtension])) {
            $this->forward('index');
        }

        $files = $this->getFilesOfExtension($selectedExtension);

        $this->view->assign('extensions', $extensions);
        $this->view->assign('selectedExtension', $selectedExtension);
        $this->view->assign('files', $files);
    }

    /**
     * @return void
     */
    public function confirmConversionAction()
    {
        $extensions = $this->getLocalExtensions();
        $selectedExtension = $this->request->hasArgument('extension') ? $this->request->getArgument('extension') : '';
        if (empty($selectedExtension) || !isset($extensions[$selectedExtension])) {
            $this->forward('index');
        }

        $files = $this->getFilesOfExtension($selectedExtension);
        $selectedFile = $this->request->hasArgument('file') ? $this->request->getArgument('file') : '';
        if (empty($selectedFile) || !isset($files[$selectedFile])) {
            $this->forward('showFiles');
        }

        $this->view->assign('extensions', $extensions);
        $this->view->assign('selectedExtension', $selectedExtension);
        $this->view->assign('files', $files);
        $this->view->assign('selectedFile', $selectedFile);
    }

    /**
     * @return void
     */
    public function convertFileAction()
    {
        $extensions = $this->getLocalExtensions();
        $selectedExtension = $this->request->hasArgument('extension') ? $this->request->getArgument('extension') : '';
        if (empty($selectedExtension) || !isset($extensions[$selectedExtension])) {
            $this->forward('index');
        }

        $files = $this->getFilesOfExtension($selectedExtension);
        $selectedFile = $this->request->hasArgument('file') ? $this->request->getArgument('file') : '';
        if (empty($selectedFile) || !isset($files[$selectedFile])) {
            $this->forward('showFiles');
        }

        $extensionPath = ExtensionManagementUtility::extPath($selectedExtension);
        if ($this->xliffFileAlreadyExists($extensionPath, $selectedFile)) {
            $this->view->assign('wasConvertedPreviously', 1);
        } elseif (strpos($selectedFile, '.xml') !== false) {
            /** @var \Evoweb\EwLlxml2xliff\Utility\Convert $convertUtility */
            $convertUtility = $this->objectManager->get(\Evoweb\EwLlxml2xliff\Utility\Convert::class);
            $messages = $convertUtility->writeXmlAsXlfFilesInPlace($selectedFile, $selectedExtension);
            if (strpos($messages, 'ERROR') === false) {
                $this->view->assign('fileConvertedSuccessfuly', 1);
                $this->view->assign('messages', $messages);
            }
            $files = $this->getFilesOfExtension($selectedExtension);
            $selectedFile = '';
        } else {
            /** @var \Evoweb\EwLlxml2xliff\Utility\Convert $convertUtility */
            $convertUtility = $this->objectManager->get(\Evoweb\EwLlxml2xliff\Utility\Convert::class);
            $messages = $convertUtility->writePhpAsXlfFilesInPlace($selectedFile, $selectedExtension);
            if (strpos($messages, 'ERROR') === false) {
                $this->view->assign('fileConvertedSuccessfuly', 1);
                $this->view->assign('messages', $messages);
            }
            $files = $this->getFilesOfExtension($selectedExtension);
            $selectedFile = '';
        }

        $this->view->assign('extensions', $extensions);
        $this->view->assign('selectedExtension', $selectedExtension);
        $this->view->assign('files', $files);
        $this->view->assign('selectedFile', $selectedFile);
    }


    /**
     * @return array
     */
    protected function getLocalExtensions()
    {
        $extensions = array_filter($this->listUtility->getAvailableExtensions(), function ($extension) {
            return $extension['type'] == 'Local';
        });
        ksort($extensions);
        array_unshift($extensions, ['key' => 'Please select']);
        return $extensions;
    }

    /**
     * Generates a selector box with file names of the currently selected extension
     *
     * @param string $extensionKey List of file extensions to select
     *
     * @return array
     */
    protected function getFilesOfExtension($extensionKey)
    {
        $extensionPath = ExtensionManagementUtility::extPath($extensionKey);

        $xmlFiles = GeneralUtility::removePrefixPathFromList(
            GeneralUtility::getAllFilesAndFoldersInPath(array(), $extensionPath, 'xml', 0),
            $extensionPath
        );
        $phpFiles = GeneralUtility::removePrefixPathFromList(
            GeneralUtility::getAllFilesAndFoldersInPath(array(), $extensionPath, 'php', 0),
            $extensionPath
        );

        if ((!is_array($xmlFiles) || empty($xmlFiles)) || (!is_array($phpFiles) || empty($phpFiles))) {
            $result = ['filename' =>  'No files to convert found in extension: "' . $extensionKey . '"'];
        } else {
            $result = [];

            if (is_array($xmlFiles)) {
                foreach ($xmlFiles as $file) {
                    if ($this->isLanguageFile($file)
                        && !$this->xliffFileAlreadyExists($extensionPath, $file)
                    ) {
                        $result[$file] = ['filename' => $file];
                    }
                }
            }

            if (is_array($phpFiles)) {
                foreach ($phpFiles as $file) {
                    if ($this->isLanguageFile($file)
                        && !$this->xliffFileAlreadyExists($extensionPath, $file)
                    ) {
                        $result[$file] = ['filename' => $file];
                    }
                }
            }

            if (!empty($result)) {
                ksort($result);
                array_unshift($result, ['filename' => 'Please select']);
            }
        }

        return $result;
    }

    /**
     * @param string $filePath
     *
     * @return bool
     */
    protected function isLanguageFile($filePath)
    {
        return strpos($filePath, 'locallang') !== false;
    }

    /**
     * @param string $extensionPath
     * @param string $filePath
     *
     * @return bool
     */
    protected function xliffFileAlreadyExists($extensionPath, $filePath)
    {
        $xliffFileName = preg_replace('#\.(xml|php)$#', '.xlf', $extensionPath . $filePath);

        return (bool) file_exists($xliffFileName);
    }
}
