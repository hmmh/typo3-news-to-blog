<?php
namespace HMMH\NewsToBlog\Domain\Model;

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

/**
 * Class TtContent
 *
 * @package HMMH\NewsToBlog\Domain\Model
 */
class TtContent extends \GeorgRinger\News\Domain\Model\TtContent
{

    /**
     * @var boolean
     */
    protected $hidden = false;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\FileReference>
     */
    protected $assets;

    /**
     * set hidden
     *
     * @param boolean $hidden
     * @return void
     */
    public function setHidden($hidden)
    {
        $this->hidden = $hidden;
    }

    /**
     * Get hidden field
     *
     * @return boolean
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage
     */
    public function getAssets()
    {
        return $this->assets;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage $assets
     */
    public function setAssets(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $assets)
    {
        $this->assets = $assets;
    }
}
