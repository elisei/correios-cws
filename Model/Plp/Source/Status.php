<?php
namespace O2TI\SigepWebCarrier\Model\Plp\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'open', 'label' => __('Open')],
            ['value' => 'processing', 'label' => __('Processing')]
        ];
    }
}