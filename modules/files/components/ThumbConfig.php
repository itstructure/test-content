<?php

namespace app\modules\files\components;

use app\modules\files\interfaces\ThumbConfigInterface;

/**
 * Class ThumbConfig
 *
 * @property string $alias
 * @property string $name
 * @property int $width
 * @property int $height
 * @property string $mode
 *
 * @package app\components
 */
class ThumbConfig implements ThumbConfigInterface
{
    /**
     * Alias name.
     *
     * @var string
     */
    public $alias;

    /**
     * Config name.
     *
     * @var string
     */
    public $name;

    /**
     * Thumb width.
     *
     * @var
     */
    public $width;

    /**
     * Thumb height.
     *
     * @var
     */
    public $height;

    /**
     * Thumb mode.
     *
     * @var
     */
    public $mode;

    /**
     * Get alias name.
     *
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * Get config name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get thumb width.
     *
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Get thumb height.
     *
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Get thumb mode.
     *
     * @return string
     */
    public function getMode(): string
    {
        return $this->mode;
    }
}
