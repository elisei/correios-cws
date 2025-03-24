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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use O2TI\SigepWebCarrier\Model\FallbackServiceUpdater;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Serialize\Serializer\Json;

class UpdateFallbackRulesCommand extends Command
{
    /**
     * @var FallbackServiceUpdater
     */
    private $fallbackUpdater;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var Json
     */
    private $json;

    /**
     * Constructor
     *
     * @param FallbackServiceUpdater $fallbackUpdater
     * @param WriterInterface $configWriter
     * @param Json $json
     * @param string|null $name
     */
    public function __construct(
        FallbackServiceUpdater $fallbackUpdater,
        WriterInterface $configWriter,
        Json $json,
        string $name = null
    ) {
        parent::__construct($name);
        $this->fallbackUpdater = $fallbackUpdater;
        $this->configWriter = $configWriter;
        $this->json = $json;
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('sigepweb:fallback:update')
            ->setDescription('Update SigepWeb fallback service rules with current API rates')
            ->addOption(
                'service',
                's',
                InputOption::VALUE_OPTIONAL,
                'Specific service code to update'
            );
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $service = $input->getOption('service');
            $output->writeln('<info>Updating fallback service rules...</info>');

            $updatedRules = $this->fallbackUpdater->updateServiceRules($service);
            
            // Save updated rules to configuration
            $this->configWriter->save(
                'carriers/sigep_web_carrier/fallback_service_rules',
                $this->json->serialize($updatedRules)
            );

            $output->writeln('<info>Fallback service rules updated successfully!</info>');
            return 1;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 0;
        }
    }
}
