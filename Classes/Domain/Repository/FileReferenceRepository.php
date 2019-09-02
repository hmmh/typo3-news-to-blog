<?php
namespace HMMH\NewsToBlog\Domain\Repository;

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

/**
 * Class FileReferenceRepository
 *
 * @package HMMH\NewsToBlog\Domain\Repository
 */
class FileReferenceRepository extends AbstractRepository
{
    /**
     * @param int $pageId
     * @param int $fileId
     *
     * @return mixed
     */
    public function getFileReferences(int $pageId, int $fileId)
    {
        $queryBuilder = $this->getQueryBuilder('sys_file_reference');
        $this->setDeletedRestrictionOnly($queryBuilder);

        return $queryBuilder->select('*')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter('pages')),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('media')),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('uid_local', $queryBuilder->createNamedParameter($fileId, \PDO::PARAM_INT))
            )
            ->setMaxResults(1)
            ->execute()
            ->fetch();
    }

    /**
     * @param int $contentUid
     * @param int $fileId
     *
     * @return mixed
     */
    public function getLocalizedAssets(int $contentUid, int $fileId)
    {
        $queryBuilder = $this->getQueryBuilder('sys_file_reference');
        $this->setDeletedRestrictionOnly($queryBuilder);

        return $queryBuilder->select('*')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter('tt_content')),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('assets')),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($contentUid, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('uid_local', $queryBuilder->createNamedParameter($fileId, \PDO::PARAM_INT))
            )
            ->setMaxResults(1)
            ->execute()
            ->fetch();
    }

    /**
     * @param int $fileId
     * @param int $pageId
     * @param FileReference $assets
     */
    public function updatePageAssets(FileReference $assets, int $pageId)
    {
        $queryBuilder = $this->getQueryBuilder('sys_file_reference');
        $queryBuilder->update('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('uid_local', $queryBuilder->createNamedParameter($assets->getFileUid(), \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter('pages')),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('media'))
            )
            ->set('title', $assets->getTitle())
            ->set('description', $assets->getDescription())
            ->set('alternative', $assets->getAlternative())
            ->execute();
    }
}
