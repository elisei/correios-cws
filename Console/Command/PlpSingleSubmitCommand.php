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
use O2TI\SigepWebCarrier\Model\Plp\PlpSingleSubmit;
use O2TI\SigepWebCarrier\Model\Plp\Source\Status as PlpStatus;

class PlpSingleSubmitCommand extends Command
{
    /**
     * Command Name
     */
    public const COMMAND_NAME = 'sigepweb:plp:single_submit';

    /**
     * Force option
     */
    public const FORCE_OPTION = 'force';

    /**
     * PPN ID argument
     */
    public const PLP_ID_ARGUMENT = 'plp_id';

    /**
     * @var PlpSingleSubmit
     */
    private $plpSingleSubmit;

    /**
     * Constructor
     *
     * @param PlpSingleSubmit $plpSingleSubmit
     * @param string|null $name
     */
    public function __construct(
        PlpSingleSubmit $plpSingleSubmit,
        $name = null
    ) {
        $this->plpSingleSubmit = $plpSingleSubmit;
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
            ->setDescription('Submit PLPs with created files to Correios API')
            ->addArgument(
                self::PLP_ID_ARGUMENT,
                InputArgument::OPTIONAL,
                'Specific PPN ID to submit'
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
                $output->writeln('<error>'. __('Please provide a PPN ID.') .'</error>');
                return 0;
            }

            $output->writeln('<info>'. __('Submitting PPN ID: %1', $plpId) .'</info>');
            
            $result = $this->plpSingleSubmit->execute($plpId);
            
            if ($result['success']) {
                $output->writeln(
                    '<info>'.
                    __(
                        '%1: PPN %2 submitted',
                        $result['message'],
                        $plpId
                    ).
                    '</info>'
                );
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
