<?php
namespace HMMH\NewsToBlog\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Sascha Wilking <sascha.wilking@hmmh.de> hmmh
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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

use GeorgRinger\News\Domain\Model\FileReference;
use GeorgRinger\News\Domain\Model\News;
use GeorgRinger\News\Domain\Model\TtContent;
use GeorgRinger\News\Domain\Repository\NewsRepository;
use HMMH\NewsToBlog\Domain\Repository\ContentRepository;
use HMMH\NewsToBlog\Domain\Repository\FileReferenceRepository;
use HMMH\NewsToBlog\Domain\Repository\LanguageRepository;
use HMMH\NewsToBlog\Domain\Repository\PagesRepository;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 * Class MigrationService
 *
 * @package HMMH\NewsToBlog\Service
 */
class MigrationService
{
    /**
     * @var int
     */
    protected $exportPid = 0;

    /**
     * @var int
     */
    protected $importPid = 0;

    /**
     * @var SymfonyStyle
     */
    protected $io = null;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var Dispatcher
     */
    protected $signalSlotDispatcher;

    /**
     * MigrationService constructor.
     *
     * @param int $exportPid
     * @param int $importPid
     * @param int $parentPid
     * @param SymfonyStyle $io
     */
    public function __construct(int $exportPid, int $importPid, SymfonyStyle $io = null)
    {
        $this->exportPid = $exportPid;
        $this->importPid = $importPid;
        $this->io = $io;

        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->signalSlotDispatcher = $this->objectManager->get(Dispatcher::class);
    }

    /**
     * Ablauf:
     *
     * 1. Es werden alle News der Default-Sprache zu Blogseiten migriert
     * 2. Die Bilder der News werden in die Seiteneigenschaften der passenden Blogseite integriert und
     *    aus dem Newsdatensatz wird ein Textmedia-Element erstellt, mit dem Header der News und dem
     *    Bodytext aus der News
     * 3. Die Inline-Contentelemente aus den Newsdatensätzen werden auf die entsprechenden Blogseiten
     *    kopiert. Da hier die Sortierung beachtet werden muss wird jedes Element einzeln gespeichert,
     *    damit es unter dem vorherigen platziert wird (wenn der gesamte Bestand auf der Seite gespeichert
     *    werden würde, wäre die Reihenfolge komplett verkehrt herum, außerdem wären dann alle Elemente
     *    per Default auf hidden)
     * 4. Es werden alle lokalisierten News ausgelesen und entsprechende Übersetzungen für die Newsseiten
     *    angelegt (per Default-Lokalisierung). Danach erst werden die lokalisierten Seiten mit den Daten
     *    aus der lokalisierten News angepasst (Titel, Metadescription etc.)
     * 5. Es werden die Contentelemente der Default-Sprache der migrierten Blogseiten lokalisiert und
     *    anschließend mit den Daten der lokalisierten News befüllt.
     *
     * @return bool
     */
    public function process()
    {
        try {
            $newsRepository = $this->objectManager->get(NewsRepository::class);

            $querySettings = $this->objectManager->get(Typo3QuerySettings::class);
            $querySettings->setRespectStoragePage(true)
                ->setStoragePageIds([$this->exportPid])
                ->setIgnoreEnableFields(true);

            $newsRepository->setDefaultQuerySettings($querySettings);
            $newsEntries = $newsRepository->findAll();
            $this->writeln(sprintf('News in default language: %d', count($newsEntries)));

            $this->createPagesForNews($newsEntries);
            $this->importFileReferencesAndContent($newsEntries);
            $this->copyNewsContentElements($newsEntries);

            $persistanceManager = $this->objectManager->get(PersistenceManager::class);
            $languageRepository = GeneralUtility::makeInstance(LanguageRepository::class);

            $languages = $languageRepository->getLanguages();
            if (is_array($languages)) {
                $sysLanguageUids = array_column($languages, 'uid');
                foreach ($sysLanguageUids as $sysLanguageUid) {
                    $this->writeln(sprintf('Migrate language: %d', $sysLanguageUid));
                    $persistanceManager->clearState();
                    $querySettings->setLanguageUid($sysLanguageUid);
                    $newsRepository->setDefaultQuerySettings($querySettings);
                    $localizedEntries = $newsRepository->findAll();
                    $this->writeln(sprintf('News in language %d: %d', $sysLanguageUid, count($newsEntries)));
                    $this->localizePages($localizedEntries);
                }
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param QueryResultInterface $newsEntries
     *
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function createPagesForNews(QueryResultInterface $newsEntries)
    {
        $pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);

        $pageData = [];

        foreach ($newsEntries as $news) {
            /** @var $news News */
            $page = $pagesRepository->getImportedPage($news->getUid());
            if (empty($page['uid'])) {
                $pageUid = StringUtility::getUniqueId('NEW');
            } else {
                $pageUid = $page['uid'];
            }

            $pageData['pages'][$pageUid]['pid'] = $this->importPid;
            $pageData['pages'][$pageUid]['doktype'] = 137;
            $pageData['pages'][$pageUid]['title'] = $news->getTitle();
            $pageData['pages'][$pageUid]['abstract'] = $news->getTeaser();
            $pageData['pages'][$pageUid]['publish_date'] = $news->getDatetime() instanceof \DateTime ? $news->getDatetime()->getTimestamp() : 0;
            $pageData['pages'][$pageUid]['archive_date'] = $news->getArchive() instanceof \DateTime ? $news->getArchive()->getTimestamp() : 0;
            $pageData['pages'][$pageUid]['keywords'] = $news->getKeywords();
            $pageData['pages'][$pageUid]['description'] = $news->getDescription();
            $pageData['pages'][$pageUid]['starttime'] = $news->getStarttime() instanceof \DateTime ? $news->getStarttime()->getTimestamp() : 0;
            $pageData['pages'][$pageUid]['endtime'] = $news->getEndtime() instanceof \DateTime ? $news->getEndtime()->getTimestamp() : 0;
            $pageData['pages'][$pageUid]['hidden'] = $news->getHidden();
            $pageData['pages'][$pageUid]['tx_newstoblog'] = $news->getUid();
            $pageData['pages'][$pageUid]['comments_active'] = 0;
            $pageData['pages'][$pageUid]['slug'] = $this->buildSlug($pageData['pages'][$pageUid], $news);

            foreach ($news->getCategories() as $category) {
                $pageData['pages'][$pageUid]['categories'][] = $category->getUid();
            }

            list($pageData) = $this->emitCreatePagesForNews($pageData, $news);
        }

        if (!empty($pageData)) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($pageData, []);
            $dataHandler->process_datamap();
        }

        $this->writeln('Pages in default language created.');
    }

    /**
     * @param QueryResultInterface $newsEntries
     *
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function localizePages(QueryResultInterface $newsEntries)
    {
        $pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);

        $pageData = [];

        foreach ($newsEntries as $news) {
            /** @var $news News */
            $page = $pagesRepository->getImportedPage($news->getL10nParent());
            if (!empty($page)) {
                $localizedPage = $pagesRepository->getImportedPage($news->getL10nParent(), $news->getSysLanguageUid());
                if (empty($localizedPage)) {
                    $cmdData = [];
                    $cmdData['pages'][$page['uid']]['localize'] = $news->getSysLanguageUid();
                    $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                    $dataHandler->start([], $cmdData);
                    $dataHandler->process_cmdmap();

                    $localizedPage = $pagesRepository->getImportedPage($news->getL10nParent(), $news->getSysLanguageUid());
                }

                if (!empty($localizedPage)) {
                    $pageUid = $localizedPage['uid'];
                    $pageData['pages'][$pageUid]['title'] = $news->getTitle();
                    $pageData['pages'][$pageUid]['abstract'] = $news->getTeaser();
                    $pageData['pages'][$pageUid]['keywords'] = $news->getKeywords();
                    $pageData['pages'][$pageUid]['description'] = $news->getDescription();
                    $pageData['pages'][$pageUid]['hidden'] = $news->getHidden();
                    $pageData['pages'][$pageUid]['slug'] = $this->buildSlug($localizedPage, $news);

                    $this->updateLocalizedPageAssets($news, $pageUid);

                    list($pageData) = $this->emitLocalizePages($pageData, $news);
                }

                $this->writeln(sprintf('Localize page: %d', $page['uid']));

                $this->localizeContent($news, $page);
            }
        }

        if (!empty($pageData)) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($pageData, []);
            $dataHandler->process_datamap();
        }
    }

    /**
     * @param News  $news
     * @param array $page
     *
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function localizeContent(News $news, array $page)
    {
        $contentRepository = GeneralUtility::makeInstance(ContentRepository::class);

        $cmdData = [];
        $content = $contentRepository->getContentFromPage($page['uid']);
        foreach ($content as $ce) {
            $localizedContent = $contentRepository->getImportedContentElement(
                $news->getL10nParent(),
                $page['uid'],
                $ce['tx_newstoblog_ce'],
                $news->getSysLanguageUid()
            );
            if (empty($localizedContent)) {
                $cmdData['tt_content'][$ce['uid']]['localize'] = $news->getSysLanguageUid();
            }

            list($cmdData) = $this->emitLocalizeContent($cmdData, $news, $ce);
        }

        if (!empty($cmdData)) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start([], $cmdData);
            $dataHandler->process_cmdmap();

            $this->writeln(sprintf('Localize content on page %d', $page['uid']));
        }

        $this->copyNewsLocalizationToContentElements($news, $page);
    }

    /**
     * @param News  $news
     * @param array $page
     *
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function copyNewsLocalizationToContentElements(News $news, array $page)
    {
        $contentRepository = GeneralUtility::makeInstance(ContentRepository::class);
        $fileReferenceRepository = GeneralUtility::makeInstance(FileReferenceRepository::class);

        $contentData = [];

        $localizedContent = $contentRepository->getImportedContentElement(
            $news->getL10nParent(),
            $page['uid'],
            0,
            $news->getSysLanguageUid()
        );

        if (!empty($localizedContent)) {
            $id = $localizedContent['uid'];
            $contentData['tt_content'][$id]['hidden'] = $news->getHidden();
            $contentData['tt_content'][$id]['header'] = $news->getTitle();
            $contentData['tt_content'][$id]['bodytext'] = $news->getBodytext();

            list($contentData) = $this->emitCopyNewsLocalizationToContentElementsDefault($contentData, $news);

            $this->writeln(sprintf('Translate localized content element (UID: %d)', $id));
        }

        $localizedRelated = $contentRepository->getImportedContentElement(
            $news->getL10nParent(),
            $page['uid'],
            -1,
            $news->getSysLanguageUid()
        );

        if (!empty($localizedRelated)) {
            $id = $localizedRelated['uid'];
            $contentData['tt_content'][$id]['hidden'] = 0;

            $link = $this->buildRelatedLinks($news);
            if (!empty($link)) {
                $list = sprintf('<ul>%s</ul>', implode('', $link));

                $contentData['tt_content'][$id]['header'] = $news->getTitle();
                $contentData['tt_content'][$id]['bodytext'] = $list;
            }

            list($contentData) = $this->emitCopyNewsLocalizationToContentElementsRelated($contentData, $news);

            $this->writeln(sprintf('Translate localized related news (UID: %d)', $id));
        }

        foreach ($news->getContentElements() as $contentElement) {
            /** @var \HMMH\NewsToBlog\Domain\Model\TtContent $contentElement */
            $localizedContent = $contentRepository->getImportedContentElement(
                $news->getL10nParent(),
                $page['uid'],
                $contentElement->getUid(),
                $news->getSysLanguageUid()
            );
            if (!empty($localizedContent)) {
                $id = $localizedContent['uid'];
                $contentData['tt_content'][$id]['hidden'] = $contentElement->getHidden();
                $contentData['tt_content'][$id]['header'] = $contentElement->getHeader();
                $contentData['tt_content'][$id]['bodytext'] = $contentElement->getBodytext();

                $this->writeln(sprintf('Translate localized content element (UID: %d)', $id));

                foreach ($contentElement->getAssets() as $asset) {
                    /** @var $asset FileReference */
                    $file = $fileReferenceRepository->getLocalizedAssets(
                        $id,
                        $asset->getOriginalResource()->getOriginalFile()->getUid()
                    );
                    if (!empty($file)) {
                        $contentData['sys_file_reference'][$file['uid']]['title'] = $asset->getOriginalResource()->getTitle();
                        $contentData['sys_file_reference'][$file['uid']]['description'] = $asset->getOriginalResource()->getDescription();
                        $contentData['sys_file_reference'][$file['uid']]['alternative'] = $asset->getOriginalResource()->getAlternative();

                        $this->writeln(sprintf('Translate file reference data (UID: %d)', $id));
                    }
                }

                list($contentData) = $this->emitCopyNewsLocalizationToContentElementsInline($contentData, $news, $contentElement);
            }
        }

        if (!empty($contentData)) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($contentData, []);
            $dataHandler->process_datamap();
        }
    }

    /**
     * @param QueryResultInterface $newsEntries
     *
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function importFileReferencesAndContent(QueryResultInterface $newsEntries)
    {
        $pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);

        $importData = [];

        foreach ($newsEntries as $news) {
            /** @var $news News */
            $page = $pagesRepository->getImportedPage($news->getUid());
            if (!empty($page)) {
                $this->addFileReferences($news, $page, $importData);
                $this->writeln(sprintf('File references added to page: %d', $page['uid']));
                $this->createOrUpdateRelatedElements($news, $page, $importData);
                $this->addNewsAsContentElement($news, $page, $importData);
                $this->writeln(sprintf('Create news content on page: %d', $page['uid']));
            }

            list($importData) = $this->emitImportFileReferencesAndContent($importData, $page, $news);
        }

        if (!empty($importData)) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($importData, []);
            $dataHandler->process_datamap();
        }
    }

    /**
     * @param News  $news
     * @param array $page
     * @param array $importData
     */
    protected function addFileReferences(News $news, array $page, array &$importData)
    {
        $fileReferenceRepository = GeneralUtility::makeInstance(FileReferenceRepository::class);

        $mediaUids = [];

        foreach ($news->getFalMedia() as $media) {
            /** @var $media FileReference */
            $fileReference = $fileReferenceRepository->getFileReferences($page['uid'], $media->getFileUid());
            if (empty($fileReference)) {
                $mediaUid = StringUtility::getUniqueId('NEW');
                $importData['sys_file_reference'][$mediaUid] = [
                    'table_local' => 'sys_file',
                    'uid_local' => $media->getOriginalResource()->getOriginalFile()->getUid(),
                    'tablenames' => 'pages',
                    'uid_foreign' => $page['uid'],
                    'fieldname' => 'media',
                    'pid' => $page['uid'],
                    'title' => $media->getTitle(),
                    'description' => $media->getDescription(),
                    'alternative' => $media->getAlternative()
                ];
                $mediaUids[] = $mediaUid;
            } else {
                $fileReferenceRepository->updatePageAssets($media, $page['uid']);
            }
        }
        $importData['pages'][$page['uid']]['media'] = implode(',', $mediaUids);
    }

    /**
     * @param News  $news
     * @param array $page
     * @param array $importData
     */
    protected function addNewsAsContentElement(News $news, array $page, array &$importData)
    {
        $contentRepository = GeneralUtility::makeInstance(ContentRepository::class);

        if ($news->getBodytext() && $news->getTitle()) {
            $content = $contentRepository->getImportedContentElement($news->getUid(), $page['uid']);
            if (!empty($content['uid'])) {
                $ttContentUid = $content['uid'];
            } else {
                $ttContentUid = StringUtility::getUniqueId('NEW');
            }
            $importData['tt_content'][$ttContentUid]['pid'] = $page['uid'];
            $importData['tt_content'][$ttContentUid]['CType'] = 'textmedia';
            $importData['tt_content'][$ttContentUid]['header'] = $news->getTitle();
            $importData['tt_content'][$ttContentUid]['header_layout'] = '1';
            $importData['tt_content'][$ttContentUid]['bodytext'] = $news->getBodytext();
            $importData['tt_content'][$ttContentUid]['tx_newstoblog'] = $news->getUid();
        }
    }

    /**
     * @param QueryResultInterface $newsEntries
     *
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function copyNewsContentElements(QueryResultInterface $newsEntries)
    {
        $pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);
        $contentRepository = GeneralUtility::makeInstance(ContentRepository::class);

        foreach ($newsEntries as $news) {
            /** @var $news News */
            $page = $pagesRepository->getImportedPage($news->getUid());
            if (!empty($page)) {
                $contentParent = $contentRepository->getImportedContentElement($news->getUid(), $page['uid']);
                foreach ($news->getContentElements() as $ce) {
                    /** @var $ce TtContent */
                    if (!empty($contentParent)) {
                        $contentParent = $this->copySingleElement($news, $ce, $page, $contentParent);
                    }
                    if (is_array($contentParent)) {
                        list($contentParent) = $this->emitCopyNewsContentElements($contentParent);
                    }
                }
            }
        }
    }

    /**
     * @param News      $news
     * @param TtContent $ce
     * @param array     $page
     * @param array     $parent
     *
     * @return bool|mixed
     */
    protected function copySingleElement(News $news, TtContent $ce, array $page, array $parent)
    {
        $contentRepository = GeneralUtility::makeInstance(ContentRepository::class);

        $cmdData = [];
        /** @var $ce TtContent */
        $content = $contentRepository->getImportedContentElement($news->getUid(), $page['uid'], $ce->getUid());
        if (empty($content)) {
            $this->writeln(sprintf('Copy News Content Element %d after Content element %d on page %d', $ce->getUid(), $parent['uid'], $page['uid']));
            $originalCe = BackendUtility::getRecord('tt_content', $ce->getUid());
            $cmdData['tt_content'][$ce->getUid()]['copy'] = [
                'action' => 'paste',
                'target' => -$parent['uid'],
                'update' => [
                    'hidden' => isset($originalCe['hidden']) ? $originalCe['hidden'] : 1,
                    'tx_newstoblog' => $news->getUid(),
                    'tx_newstoblog_ce' => $ce->getUid()
                ]
            ];

            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start([], $cmdData);
            $dataHandler->process_cmdmap();

            return $contentRepository->getImportedContentElement($news->getUid(), $page['uid'], $ce->getUid());
        }

        return false;
    }

    /**
     * @param News  $news
     * @param array $page
     * @param array $importData
     */
    protected function createOrUpdateRelatedElements(News $news, array $page, array &$importData)
    {
        $contentRepository = GeneralUtility::makeInstance(ContentRepository::class);

        $link = $this->buildRelatedLinks($news);

        if (!empty($link)) {
            $list = sprintf('<ul>%s</ul>', implode('', $link));

            $content = $contentRepository->getImportedContentElement($news->getUid(), $page['uid'], -1, $news->getSysLanguageUid());
            if (!empty($content['uid'])) {
                $ttContentUid = $content['uid'];
            } else {
                $ttContentUid = StringUtility::getUniqueId('NEW');
            }

            $importData['tt_content'][$ttContentUid]['pid'] = $page['uid'];
            $importData['tt_content'][$ttContentUid]['CType'] = 'textmedia';
            $importData['tt_content'][$ttContentUid]['header'] = $news->getTitle();
            $importData['tt_content'][$ttContentUid]['header_layout'] = '100';
            $importData['tt_content'][$ttContentUid]['bodytext'] = $list;
            $importData['tt_content'][$ttContentUid]['tx_newstoblog'] = $news->getUid();
            $importData['tt_content'][$ttContentUid]['tx_newstoblog_ce'] = -1;
        }
    }

    /**
     * @param News $news
     *
     * @return array
     */
    protected function buildRelatedLinks(News $news)
    {
        $pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);

        $link = [];
        foreach ($news->getRelatedSorted() as $related) {
            /** @var News $related */
            $relatedPage = $pagesRepository->getImportedPage($related->getUid());
            if (!empty($relatedPage)) {
                $link[] = sprintf('<li><a href="t3://page?uid=%d">%s</a></li>', $relatedPage['uid'], $related->getTitle());
            }
        }

        return $link;
    }

    /**
     * @param News $news
     * @param int  $pageUid
     */
    protected function updateLocalizedPageAssets(News $news, int $pageUid)
    {
        $fileReferenceRepository = GeneralUtility::makeInstance(FileReferenceRepository::class);

        foreach ($news->getFalMedia() as $falMedia) {
            /** @var $falMedia FileReference */
            $fileReferenceRepository->updatePageAssets($falMedia, $pageUid);
        }
    }

    /**
     * @param array $page
     * @param News  $news
     *
     * @return string
     */
    protected function buildSlug(array $page, News $news)
    {
        $fieldConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'];
        $slugHelper = GeneralUtility::makeInstance(SlugHelper::class, 'pages', 'slug', $fieldConfig);
        $slugGenerated = $slugHelper->generate($page, $page['pid']);
        $slugSegments = explode('/', $slugGenerated);
        array_pop($slugSegments);
        $slugSegments[] = $news->getPathSegment();

        return implode('/', $slugSegments);
    }

    /**
     * @param string $message
     */
    protected function writeln(string $message)
    {
        if ($this->io instanceof SymfonyStyle) {
            $this->io->writeln($message);
        }
    }

    /**
     * @param string $message
     */
    protected function error(string $message)
    {
        if ($this->io instanceof SymfonyStyle) {
            $this->io->error($message);
        }
    }

    /**
     * @param array $pageData
     * @param News  $news
     *
     * @return array
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function emitCreatePagesForNews(array $pageData, News $news)
    {
        return $this->signalSlotDispatcher->dispatch(
            self::class,
            'createPagesForNews',
            [$pageData, $news, $this]
        );
    }

    /**
     * @param array $importData
     * @param array $page
     * @param News  $news
     *
     * @return array
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function emitImportFileReferencesAndContent(array $importData, array $page, News $news)
    {
        return $this->signalSlotDispatcher->dispatch(
            self::class,
            'importFileReferencesAndContent',
            [$importData, $page, $news, $this]
        );
    }

    /**
     * @param array $contentParent
     *
     * @return array
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function emitCopyNewsContentElements(array $contentParent)
    {
        return $this->signalSlotDispatcher->dispatch(
            self::class,
            'copyNewsContentElements',
            [$contentParent, $this]
        );
    }

    /**
     * @param array $pageData
     * @param News  $news
     *
     * @return array
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function emitLocalizePages(array $pageData, News $news)
    {
        return $this->signalSlotDispatcher->dispatch(
            self::class,
            'localizePages',
            [$pageData, $news, $this]
        );
    }

    /**
     * @param array $cmdData
     * @param News  $news
     * @param array $ce
     *
     * @return array
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function emitLocalizeContent(array $cmdData, News $news, array $ce)
    {
        return $this->signalSlotDispatcher->dispatch(
            self::class,
            'localizePages',
            [$cmdData, $news, $ce, $this]
        );
    }

    /**
     * @param array $contentData
     * @param News  $news
     *
     * @return array
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function emitCopyNewsLocalizationToContentElementsDefault(array $contentData, News $news)
    {
        return $this->signalSlotDispatcher->dispatch(
            self::class,
            'copyNewsLocalizationToContentElementsDefault',
            [$contentData, $news, $this]
        );
    }

    /**
     * @param $contentData
     * @param $news
     *
     * @return array
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function emitCopyNewsLocalizationToContentElementsRelated($contentData, $news)
    {
        return $this->signalSlotDispatcher->dispatch(
            self::class,
            'copyNewsLocalizationToContentElementsRelated',
            [$contentData, $news, $this]
        );
    }

    /**
     * @param array     $contentData
     * @param News      $news
     * @param TtContent $contentElement
     *
     * @return array
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function emitCopyNewsLocalizationToContentElementsInline(array $contentData, News $news, TtContent $contentElement)
    {
        return $this->signalSlotDispatcher->dispatch(
            self::class,
            'copyNewsLocalizationToContentElementsInline',
            [$contentData, $news, $contentElement, $this]
        );
    }
}
