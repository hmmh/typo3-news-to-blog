<?php
namespace HMMH\NewsToBlog\Hook;

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
 * Class DataHandler
 *
 * @package HMMH\NewsToBlog\Hook
 */
class DataHandler
{

    /**
     * Da $value bei einem "paste"-Kommando überschrieben wird und ignoreLocalization dann
     * nicht ausgewertet werden kann wird es durch den Hook neu gesetzt
     *
     * @param $command
     * @param $table
     * @param $id
     * @param $value
     * @param $dataHandler
     * @param $pasteUpdate
     */
    public function processCmdmap_preProcess($command, $table, $id, &$value, $dataHandler, $pasteUpdate)
    {
        if (is_array($pasteUpdate) && isset($pasteUpdate['tx_newstoblog']) && $command === 'copy' && is_int($value)) {
            $target = $value;
            $value = null;
            $value['target'] = $target;
            $value['ignoreLocalization'] = true;
        }
    }
}
