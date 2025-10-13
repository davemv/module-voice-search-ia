<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Console;

use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCorrections extends Command
{
    public function __construct(private ResourceConnection $resource, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('voice:export:corrections');
        $this->setDescription('Export voice search corrections to CSV');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $conn  = $this->resource->getConnection();
        $table = $conn->getTableName('voice_search_corrections');

        $rows = $conn->fetchAll("SELECT raw_term, corrected_term FROM {$table} ORDER BY raw_term");

        $path = BP . '/var/export/voice_corrections.csv';
        @mkdir(dirname($path), 0775, true);
        $fp = fopen($path, 'w');
        fputcsv($fp, ['raw_term', 'corrected_term']);
        foreach ($rows as $r) {
            fputcsv($fp, [$r['raw_term'], $r['corrected_term']]);
        }
        fclose($fp);

        $output->writeln("<info>Exportado a {$path}</info>");
        return Command::SUCCESS;
    }
}
