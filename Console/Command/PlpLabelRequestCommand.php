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
use O2TI\SigepWebCarrier\Model\Plp\PlpLabelRequest;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;

class PlpLabelRequestCommand extends Command
{
    /**
     * Command Name
     */
    public const COMMAND_NAME = 'sigepweb:plp:request_labels';

    /**
     * Force option
     */
    public const FORCE_OPTION = 'force';

    /**
     * PLP ID argument
     */
    public const PLP_ID_ARGUMENT = 'plp_id';

    /**
     * @var PlpLabelRequest
     */
    private $plpLabelRequest;

    /**
     * Constructor
     *
     * @param PlpLabelRequest $plpLabelRequest
     * @param string|null $name
     */
    public function __construct(
        PlpLabelRequest $plpLabelRequest,
        $name = null
    ) {
        $this->plpLabelRequest = $plpLabelRequest;
        
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
            ->setDescription('Request labels for PLPs with submitted objects')
            ->addArgument(
                self::PLP_ID_ARGUMENT,
                InputArgument::OPTIONAL,
                'Specific PLP ID to request labels for'
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
                $output->writeln(
                    '<error>'.
                    __('Please provide a PLP ID or use the --all option to process all PLPs')
                    .'</error>'
                );
                return 0;
            }

            $output->writeln('<info>'. __('Requesting labels for PLP ID: %1', $plpId) .'</info>');
            
            $result = $this->plpLabelRequest->execute($plpId);
            
            if ($result['success']) {
                $output->writeln(
                    '<info>'.
                    __('%1', $result['message']).
                    '</info>'
                );
                
                if (!empty($result['data']['receipts'])) {
                    $output->writeln('<info>'. __('Label receipt details:') .'</info>');
                    foreach ($result['data']['receipts'] as $receipt) {
                        $output->writeln(
                            '<comment>'.
                            __(
                                '  - Order ID: %1, Tracking: %2, Receipt ID: %3',
                                $receipt['plp_order_id'],
                                $receipt['tracking_code'],
                                $receipt['receipt_id']
                            ).
                            '</comment>'
                        );
                    }
                }
                return 1;
            }

            if (!$result['success']) {
                $output->writeln('<error>'. __('%1', $result['message']) .'</error>');
                return 0;
            }

        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 0;
        }
    }
}
