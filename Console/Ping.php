<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Ping extends Command
{
    public function __construct(string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('voice:ping');
        $this->setDescription('Simple ping command to verify command registration');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>voice:ping OK</info>');
        return Command::SUCCESS;
    }
}
