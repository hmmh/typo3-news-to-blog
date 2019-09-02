# News to blog

This extension contains a simple migration script to convert news entries (EXT:news) to
blog pages (EXT:blog).

## Installation

Installation can be done via composer.

    composer require hmmh/news-to-blog

## Execute

Execute the migration on CLI:

    vendor/bin/typo3 blog:import_news <news> <blog>

#### Arguments
- news: the page id where the news are stored (export page)
- blog: the page id where the blog pages should be stored  (import page)

## Features

- A blog page is created for each news entry in default language
- A content element is created for the news which contains the header and the bodytext
- A content element is created for the related news 
- FAL entries are stored in the blog page properties
- Content elements are created for all inline records of the news entries
- All pages and content elements will be localized 
