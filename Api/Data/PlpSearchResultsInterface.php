<?php
namespace O2TI\SigepWebCarrier\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface PlpSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get PLPs list.
     *
     * @return \O2TI\SigepWebCarrier\Api\Data\PlpInterface[]
     */
    public function getItems();

    /**
     * Set PLPs list.
     *
     * @param \O2TI\SigepWebCarrier\Api\Data\PlpInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}