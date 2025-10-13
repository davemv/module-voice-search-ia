<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Console;

use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCorrections extends Command
{
    const ARG_PATH = 'path';

    public function __construct(private ResourceConnection $resource, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('voice:import:corrections');
        $this->setDescription('Import voice search corrections from CSV (columns: raw_term, corrected_term)');
        $this->addArgument(self::ARG_PATH, InputArgument::OPTIONAL, 'CSV path', BP . '/var/import/voice_corrections.csv');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = (string)$input->getArgument(self::ARG_PATH);
        if (!is_file($path)) {
            $output->writeln("<error>No existe el archivo: {$path}</error>");
            return Command::FAILURE;
        }

        $conn  = $this->resource->getConnection();
        $table = $conn->getTableName('voice_search_corrections');

        $updated = 0; $skipped = 0; $rownum = 0;
        if (($fp = fopen($path, 'r')) !== false) {
            while (($row = fgetcsv($fp)) !== false) {
                $rownum++;
                if ($rownum === 1 && isset($row[0]) && strtolower(trim($row[0])) === 'raw_term') {
                    continue; // header
                }
                $raw  = isset($row[0]) ? trim($row[0]) : '';
                $corr = isset($row[1]) ? trim($row[1]) : '';

                if ($raw === '' || $corr === '') { $skipped++; continue; }

                try {
                    $conn->insertOnDuplicate(
                        $table,
                        ['raw_term' => $raw, 'corrected_term' => $corr],
                        ['corrected_term']
                    );
                    $updated++;
                } catch (\Throwable $e) {
                    $skipped++;
                }
            }
            fclose($fp);
        }

        $output->writeln('<info>Import terminado.</info>');
        $output->writeln("Insert/Update: {$updated} | Skipped: {$skipped}");
        return Command::SUCCESS;
    }
}
