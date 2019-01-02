<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Psr\Log\LoggerInterface;


// run via command line: php bin/console import:wiki_related --no-debug
class WikiLinkRelated extends Command
{

  protected function configure()
  {
    $this
        // the name of the command (the part after "bin/console")
        ->setName('import:wiki_related')

        // the short description shown while running "php bin/console list"
        ->setDescription('Imports related links from wiki')

        // the full command description shown when running the command with
        // the "--help" option
        ->setHelp('This command imports from wikirelation table')
    ;
  }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tiw = new \App\Controller\TaxonomyWikiLinkRelated();
        echo $tiw->import();

    }
}
