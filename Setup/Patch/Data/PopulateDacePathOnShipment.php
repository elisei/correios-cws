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
use Magento\Framework\Serialize\Serializer\Json;

class PopulateDacePathOnShipment implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param Json $json
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        Json $json
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->json = $json;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $plpOrderTable = $this->moduleDataSetup->getTable('sales_shipment_correios_plp_order');
        $shipmentTable = $this->moduleDataSetup->getTable('sales_shipment');
        $shipmentGridTable = $this->moduleDataSetup->getTable('sales_shipment_grid');

        $select = $connection->select()
            ->from($plpOrderTable, ['shipment_id', 'processing_data'])
            ->where('shipment_id IS NOT NULL')
            ->where('processing_data IS NOT NULL');

        $rows = $connection->fetchAll($select);

        foreach ($rows as $row) {
            try {
                $processingData = $this->json->unserialize($row['processing_data']);
            } catch (\Exception $e) {
                continue;
            }

            if (empty($processingData['receiptFileName'])) {
                continue;
            }

            $dacePath = $processingData['receiptFileName'];

            $connection->update(
                $shipmentTable,
                ['sigepweb_dace_path' => $dacePath],
                ['entity_id = ?' => $row['shipment_id']]
            );

            $connection->update(
                $shipmentGridTable,
                ['sigepweb_dace_path' => $dacePath],
                ['entity_id = ?' => $row['shipment_id']]
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
