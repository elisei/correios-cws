<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use O2TI\SigepWebCarrier\Model\Plp\PlpDaceDownload;

class PlpDaceDownloadCommand extends Command
{
    /**
     * Command Name
     */
    public const COMMAND_NAME = 'sigepweb:plp:download_dace';

    /**
     * PPN ID argument
     */
    public const PLP_ID_ARGUMENT = 'plp_id';

    /**
     * @var PlpDaceDownload
     */
    private $plpDaceDownload;

    /**
     * @param PlpDaceDownload $plpDaceDownload
     * @param string|null $name
     */
    public function __construct(
        PlpDaceDownload $plpDaceDownload,
        $name = null
    ) {
        $this->plpDaceDownload = $plpDaceDownload;
        parent::__construct($name);
    }

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Download DACE (DC-e receipt) for PPN orders')
            ->addArgument(
                self::PLP_ID_ARGUMENT,
                InputArgument::REQUIRED,
                'PPN ID to download DACE for'
            );

        parent::configure();
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $plpId = $input->getArgument(self::PLP_ID_ARGUMENT);

            $output->writeln('<info>' . __('Downloading DACE for PPN ID: %1', $plpId) . '</info>');

            $result = $this->plpDaceDownload->execute($plpId);

            if ($result['success']) {
                $output->writeln('<info>' . __('%1', $result['message']) . '</info>');

                if (!empty($result['data']['files'])) {
                    $output->writeln('<info>' . __('DACE files:') . '</info>');
                    foreach ($result['data']['files'] as $file) {
                        $output->writeln(
                            '<comment>' .
                            __(
                                '  - Order ID: %1, Tracking: %2, File: %3',
                                $file['plp_order_id'],
                                $file['tracking_code'],
                                $file['file_name']
                            ) .
                            '</comment>'
                        );
                    }
                }

                return Command::SUCCESS;
            }

            $output->writeln('<error>' . __('%1', $result['message']) . '</error>');
            return Command::FAILURE;

        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
