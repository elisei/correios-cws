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

namespace O2TI\SigepWebCarrier\Api\Data;

interface SigepWebServicesInterface
{
    /**#@+
     * Constants for keys of data array.
     */
    public const ENTITY_ID = 'entity_id';
    public const CODE = 'code';
    public const NAME = 'name';
    public const CATEGORY = 'category';
    public const STATUS = 'status';
    public const DECLARED_MIN_VALUE = 'declared_min_value';
    public const DECLARED_MAX_VALUE = 'declared_max_value';
    public const STORE_NAME = 'store_name';
    public const HAS_MP = 'has_mp';
    public const HAS_AR = 'has_ar';
    public const HAS_VD = 'has_vd';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    /**#@-*/

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId();

    /**
     * Set ID
     *
     * @param int $id
     * @return $this
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function setId($id);

    /**
     * Get Code
     *
     * @return string|null
     */
    public function getCode();

    /**
     * Set Code
     *
     * @param string $code
     * @return $this
     */
    public function setCode($code);

    /**
     * Get Name
     *
     * @return string|null
     */
    public function getName();

    /**
     * Set Name
     *
     * @param string $name
     * @return $this
     */
    public function setName($name);

    /**
     * Get Category
     *
     * @return string|null
     */
    public function getCategory();

    /**
     * Set Category
     *
     * @param string $category
     * @return $this
     */
    public function setCategory($category);

    /**
     * Get Status
     *
     * @return int|null
     */
    public function getStatus();

    /**
     * Set Status
     *
     * @param int $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * Get Declared Min Value
     *
     * @return float|null
     */
    public function getDeclaredMinValue();

    /**
     * Set Declared Min Value
     *
     * @param float $value
     * @return $this
     */
    public function setDeclaredMinValue($value);

    /**
     * Get Declared Max Value
     *
     * @return float|null
     */
    public function getDeclaredMaxValue();

    /**
     * Set Declared Max Value
     *
     * @param float $value
     * @return $this
     */
    public function setDeclaredMaxValue($value);

    /**
     * Get Store Name
     *
     * @return string|null
     */
    public function getStoreName();

    /**
     * Set Store Name
     *
     * @param string $storeName
     * @return $this
     */
    public function setStoreName($storeName);

    /**
     * Get Has MP
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getHasMp();

    /**
     * Set Has MP
     *
     * @param bool $hasMp
     * @return $this
     */
    public function setHasMp($hasMp);

    /**
     * Get Has AR
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getHasAr();

    /**
     * Set Has AR
     *
     * @param bool $hasAr
     * @return $this
     */
    public function setHasAr($hasAr);

    /**
     * Get Has VD
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getHasVd();

    /**
     * Set Has VD
     *
     * @param bool $hasVd
     * @return $this
     */
    public function setHasVd($hasVd);

    /**
     * Load Sigep Service by Code
     *
     * @param string $code
     * @return $this
     */
    public function loadByCode($code);
}
