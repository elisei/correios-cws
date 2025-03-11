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
use O2TI\SigepWebCarrier\Model\Plp\PlpOrderShipmentCreator;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;

class PlpShipmentCreateCommand extends Command
{
    /**
     * Command Name
     */
    public const COMMAND_NAME = 'sigepweb:plp:create_shipments';

    /**
     * Force option
     */
    public const FORCE_OPTION = 'force';

    /**
     * PLP ID argument
     */
    public const PLP_ID_ARGUMENT = 'plp_id';

    /**
     * @var PlpOrderShipmentCreator
     */
    private $shipmentCreator;

    /**
     * Constructor
     *
     * @param PlpOrderShipmentCreator $shipmentCreator
     * @param string|null $name
     */
    public function __construct(
        PlpOrderShipmentCreator $shipmentCreator,
        $name = null
    ) {
        $this->shipmentCreator = $shipmentCreator;
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
            ->setDescription('Create shipments for PLP orders with labels')
            ->addArgument(
                self::PLP_ID_ARGUMENT,
                InputArgument::OPTIONAL,
                'Specific PLP ID to create shipments for'
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
                $output->writeln('<e>'. __('Please provide a PLP ID') .'</e>');
                return 0;
            }

            $output->writeln('<info>'. __('Creating shipments for PLP ID: %1', $plpId) .'</info>');
                
            $result = $this->shipmentCreator->execute($plpId);
            
            if ($result['success']) {
                $output->writeln(
                    '<info>'.
                    __('%1', $result['message']).
                    '</info>'
                );
                
                if (!empty($result['data']['shipments'])) {
                    $output->writeln('<info>'. __('Shipment details:') .'</info>');
                    foreach ($result['data']['shipments'] as $shipment) {
                        $status = $shipment['status'];
                        $statusText = isset($shipment['error']) ?
                            __('%1: %2', $status, $shipment['error']) :
                            $status;
                        
                        $output->writeln(
                            '<comment>'.
                            __(
                                '  - Order ID: %1, Shipment ID: %2, Status: %3',
                                $shipment['order_id'],
                                $shipment['shipment_id'] ?? __('N/A'),
                                $statusText
                            ).
                            '</comment>'
                        );
                    }
                }
                
                return 1;
            }

            if (!$result['success']) {
                $output->writeln('<e>'. __('%1', $result['message']) .'</e>');
                return 0;
            }
            
        } catch (\Exception $e) {
            $output->writeln('<e>' . $e->getMessage() . '</e>');
            return 0;
        }
    }
}
