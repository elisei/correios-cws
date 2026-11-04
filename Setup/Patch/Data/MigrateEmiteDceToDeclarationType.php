<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

namespace O2TI\SigepWebCarrier\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use O2TI\SigepWebCarrier\Model\Config\Source\DeclarationType;

/**
 * Converts the old emite_dce Yes/No into declaration_type, keeping each scope:
 * 1 => dce, 0 => nfe_invoice. Scopes without emite_dce get the new default (nfe_invoice).
 */
class MigrateEmiteDceToDeclarationType implements DataPatchInterface
{
    private const OLD_PATH = 'carriers/sigep_web_carrier/emite_dce';
    private const NEW_PATH = 'carriers/sigep_web_carrier/declaration_type';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['scope', 'scope_id', 'value'])
                ->where('path = ?', self::OLD_PATH)
        );

        foreach ($rows as $row) {
            $connection->insertOnDuplicate(
                $table,
                [
                    'scope' => $row['scope'],
                    'scope_id' => $row['scope_id'],
                    'path' => self::NEW_PATH,
                    'value' => (string)$row['value'] === '1'
                        ? DeclarationType::DCE
                        : DeclarationType::NFE_INVOICE,
                ],
                ['value']
            );
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
