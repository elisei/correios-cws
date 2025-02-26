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
use O2TI\SigepWebCarrier\Model\PlpDataCollector;

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
                self::ALL_PLPS_OPTION,
                'a',
                InputOption::VALUE_NONE,
                'Process all open PLPs with pending orders'
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
            $processAll = $input->getOption(self::ALL_PLPS_OPTION);
            
            if (!$plpId && !$processAll) {
                $output->writeln('<error>Please provide a PLP ID or use the --all option to process all PLPs</error>');
                return Command::FAILURE;
            }

            if ($processAll) {
                $output->writeln('<info>Processing all open PLPs with pending orders...</info>');
                
                $plps = $this->plpDataCollector->getOpenPlpsWithPendingOrders();
                
                if ($plps->getSize() === 0) {
                    $output->writeln('<info>No open PLPs with pending orders found</info>');
                    return Command::SUCCESS;
                }
                
                $totalProcessed = 0;
                $totalErrors = 0;
                
                foreach ($plps as $plp) {
                    $output->writeln(sprintf('<info>Processing PLP ID: %s</info>', $plp->getId()));
                    
                    $result = $this->plpDataCollector->execute($plp->getId());
                    
                    $totalProcessed += $result['processed'];
                    $totalErrors += $result['errors'];
                    
                    if ($result['success']) {
                        $output->writeln(
                            sprintf(
                                '<info>%s: Processed %d orders with %d errors.</info>',
                                $result['message'],
                                $result['processed'],
                                $result['errors']
                            )
                        );
                    } else {
                        $output->writeln(sprintf('<error>%s</error>', $result['message']));
                    }
                }
                
                $output->writeln(
                    sprintf(
                        '<info>Total: Processed %d orders with %d errors across all PLPs.</info>',
                        $totalProcessed,
                        $totalErrors
                    )
                );
                
                if ($totalErrors > 0) {
                    $output->writeln('<comment>Check logs for error details.</comment>');
                }
                
                return Command::SUCCESS;
            } else {
                $output->writeln(sprintf('<info>Processing PLP ID: %s</info>', $plpId));
                
                $result = $this->plpDataCollector->execute($plpId);
                
                if ($result['success']) {
                    $output->writeln(
                        sprintf(
                            '<info>%s: Processed %d orders with %d errors.</info>',
                            $result['message'],
                            $result['processed'],
                            $result['errors']
                        )
                    );
                    
                    if ($result['errors'] > 0) {
                        $output->writeln('<comment>Check logs for error details.</comment>');
                    }
                    
                    return Command::SUCCESS;
                } else {
                    $output->writeln(sprintf('<error>%s</error>', $result['message']));
                    return Command::FAILURE;
                }
            }
            
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
