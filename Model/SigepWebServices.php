<?php
/**
 * O2TI Sigep Web Carrier.
 *
 * Copyright © 2025 O2TI. All rights reserved.
 *
 * @author    Bruno Elisei <brunoelisei@o2ti.com>
 * @license   See LICENSE for license details.
 */

declare(strict_types=1);

namespace O2TI\SigepWebCarrier\Model;

use Magento\Framework\Model\AbstractModel;
use O2TI\SigepWebCarrier\Model\ResourceModel\SigepWebServices as ResourceModel;
use O2TI\SigepWebCarrier\Api\Data\SigepWebServicesInterface;

/**
 * Sigep Web Serivices.
 *
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class SigepWebServices extends AbstractModel implements SigepWebServicesInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'sigep_web_services_model';

    /**
     * Initialize magento model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId()
    {
        return $this->getData('entity_id');
    }

    /**
     * Set ID
     *
     * @param int $id
     * @return $this
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function setId($id)
    {
        return $this->setData('entity_id', $id);
    }

    /**
     * Get Code
     *
     * @return string|null
     */
    public function getCode()
    {
        return $this->getData('code');
    }

    /**
     * Set Code
     *
     * @param string $code
     * @return $this
     */
    public function setCode($code)
    {
        return $this->setData('code', $code);
    }

    /**
     * Get Name
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->getData('name');
    }

    /**
     * Set Name
     *
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        return $this->setData('name', $name);
    }

    /**
     * Get Category
     *
     * @return string|null
     */
    public function getCategory()
    {
        return $this->getData('category');
    }

    /**
     * Set Category
     *
     * @param string $category
     * @return $this
     */
    public function setCategory($category)
    {
        return $this->setData('category', $category);
    }

    /**
     * Get Status
     *
     * @return int|null
     */
    public function getStatus()
    {
        return $this->getData('status');
    }

    /**
     * Set Status
     *
     * @param int $status
     * @return $this
     */
    public function setStatus($status)
    {
        return $this->setData('status', $status);
    }

    /**
     * Get Declared Min Value
     *
     * @return float|null
     */
    public function getDeclaredMinValue()
    {
        return $this->getData('declared_min_value');
    }

    /**
     * Set Declared Min Value
     *
     * @param float $value
     * @return $this
     */
    public function setDeclaredMinValue($value)
    {
        return $this->setData('declared_min_value', $value);
    }

    /**
     * Get Declared Max Value
     *
     * @return float|null
     */
    public function getDeclaredMaxValue()
    {
        return $this->getData('declared_max_value');
    }

    /**
     * Set Declared Max Value
     *
     * @param float $value
     * @return $this
     */
    public function setDeclaredMaxValue($value)
    {
        return $this->setData('declared_max_value', $value);
    }

    /**
     * Get Store Name
     *
     * @return string|null
     */
    public function getStoreName()
    {
        return $this->getData('store_name');
    }

    /**
     * Set Store Name
     *
     * @param string $storeName
     * @return $this
     */
    public function setStoreName($storeName)
    {
        return $this->setData('store_name', $storeName);
    }

    /**
     * Get Created At
     *
     * @return string|null
     */
    public function getCreatedAt()
    {
        return $this->getData('created_at');
    }

    /**
     * Get Updated At
     *
     * @return string|null
     */
    public function getUpdatedAt()
    {
        return $this->getData('updated_at');
    }

    /**
     * Get Has MP (Mão Própria)
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getHasMp()
    {
        return (bool)$this->getData('has_mp');
    }

    /**
     * Set Has MP (Mão Própria)
     *
     * @param bool $hasMP
     * @return $this
     */
    public function setHasMp($hasMP)
    {
        return $this->setData('has_mp', (bool)$hasMP);
    }

    /**
     * Get Has AR (Aviso Recebimento)
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getHasAr()
    {
        return (bool)$this->getData('has_ar');
    }

    /**
     * Set Has AR (Aviso Recebimento)
     *
     * @param bool $hasAR
     * @return $this
     */
    public function setHasAr($hasAR)
    {
        return $this->setData('has_ar', (bool)$hasAR);
    }

    /**
     * Get Has VD (Valor Declarado)
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getHasVd()
    {
        return (bool)$this->getData('has_vd');
    }

    /**
     * Set Has VD (Valor Declarado)
     *
     * @param bool $hasVD
     * @return $this
     */
    public function setHasVd($hasVD)
    {
        return $this->setData('has_vd', (bool)$hasVD);
    }

    /**
     * Load Sigep Service by Code
     *
     * @param string $code
     * @return $this
     */
    public function loadByCode($code)
    {
        $this->_getResource()->load($this, $code, 'code');
        return $this;
    }
}
