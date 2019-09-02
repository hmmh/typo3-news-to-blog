<?php
namespace HMMH\NewsToBlog\Command;

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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use HMMH\NewsToBlog\Service\MigrationService;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class MoveNewsToBlogCommand
 *
 * @package HMMH\NewsToBlog\Command
 */
class MoveNewsToBlogCommand extends Command
{
    /**
     * @var int
     */
    public $blogPid = 0;

    /**
     * @var int
     */
    public $newsPid = 0;

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this
            ->setDescription('Migrate News entries to blog pages')
            ->addArgument(
                'news',
                InputArgument::REQUIRED,
                'ID of the news folder (export)'
            )
            ->addArgument(
                'blog',
                InputArgument::REQUIRED,
                'ID of the blog folder (import)'
            );
    }

    /**
     * Executes the command for adding the lock file
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception\NotImplementedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = time();
        Bootstrap::initializeBackendAuthentication();

        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $migrationService = GeneralUtility::makeInstance(
            MigrationService::class,
            (int)$input->getArgument('news'),
            (int)$input->getArgument('blog'),
            $io
        );
        $result = $migrationService->process();

        $end = time();
        if ($result === true) {
            $io->success(sprintf('Migration successful in %d seconds', $end - $start));
        } else {
            $io->error(sprintf('Failure after %d seconds', $end - $start));
        }
    }
}
