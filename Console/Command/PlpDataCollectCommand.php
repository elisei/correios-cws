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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use O2TI\SigepWebCarrier\Model\Plp\PlpDataCollector;

class PlpDataCollectCommand extends Command
{
    /**
     * Command Name
     */
    public const COMMAND_NAME = 'sigepweb:plp:collect';

    /**
     * Force option
     */
    public const FORCE_OPTION = 'force';

    /**
     * PLP ID argument
     */
    public const PLP_ID_ARGUMENT = 'plp_id';

    /**
     * Process all PLPs option
     */
    public const ALL_PLPS_OPTION = 'all';

    /**
     * @var PlpDataCollector
     */
    private $plpDataCollector;

    /**
     * Constructor
     *
     * @param PlpDataCollector $plpDataCollector
     * @param string|null $name
     */
    public function __construct(
        PlpDataCollector $plpDataCollector,
        $name = null
    ) {
        $this->plpDataCollector = $plpDataCollector;
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
            ->setDescription('Collect order data for PLPs with pending orders')
            ->addArgument(
                self::PLP_ID_ARGUMENT,
                InputArgument::OPTIONAL,
                'Specific PLP ID to collect data for'
            )
            ->addOption(
                self::FORCE_OPTION,
                'f',
                InputOption::VALUE_NONE,
                'Force execution even if already running'
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

            if (!$plpId) {
                $output->writeln('<error>'. __('Please provide a PLP ID') . '</error>');
                return 0;
            }

            $output->writeln('<info>' . __('Processing PLP ID: %1', $plpId) . '</info>');
            
            $result = $this->plpDataCollector->execute($plpId);
            
            if ($result['success']) {
                $output->writeln(
                    '<info>'.
                    __(
                        '%1: Processed %2 orders with %3 errors.',
                        $result['message'],
                        $result['processed'],
                        $result['errors']
                    )
                    .'</info>'
                );
                
                if ($result['errors'] > 0) {
                    $output->writeln('<comment>'. __('Check logs for error details.').'</comment>');
                }
                
                return 1;
            }

            if (!$result['success']) {
                $output->writeln('<error>' . __('Error: %s', $result['message']) . '</error>');
                return 0;
            }
            
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 0;
        }
    }
}
