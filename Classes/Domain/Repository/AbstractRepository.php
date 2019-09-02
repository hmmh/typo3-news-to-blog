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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class AbstractRepository
 *
 * @package HMMH\NewsToBlog\Domain\Repository
 */
abstract class AbstractRepository
{

    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * @param string $table
     *
     * @return QueryBuilder
     */
    protected function getQueryBuilder(string $table) : QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
    }

    /**
     * @param QueryBuilder $queryBuilder
     */
    protected function setDeletedRestrictionOnly(QueryBuilder $queryBuilder) : void
    {
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
    }
}
