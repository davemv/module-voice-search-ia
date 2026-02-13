<?php
declare(strict_types=1);
namespace NTT\VoiceSearch\Console;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use NTT\VoiceSearch\Model\Ai\Embedder;

class DiagnoseAi extends Command
{
    public function __construct(private Embedder $embedder, string $name=null){ parent::__construct($name); }

    protected function configure()
    {
        $this->setName('voice:ai:ping')
            ->setDescription('Test AI embedding provider')
            ->addArgument('text', InputArgument::REQUIRED, 'Text to embed');
    }

    protected function execute(InputInterface $in, OutputInterface $out)
    {
        $text = (string)$in->getArgument('text');
        $vec  = $this->embedder->embedQuery($text);
        if (!$vec) {
            $out->writeln('<error>Embedding EMPTY (check API key/credits or see var/log/ntt_voice_ai.log)</error>');
            return Cli::RETURN_FAILURE;
        }
        $out->writeln('<info>OK. Vector length: '.count($vec).'</info>');
        return Cli::RETURN_SUCCESS;
    }
}
