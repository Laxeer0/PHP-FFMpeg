<?php

/*
 * This file is part of PHP-FFmpeg.
 *
 * (c) Alchemy <dev.team@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FFMpeg\Filters\Video;

use FFMpeg\Coordinate\Dimension;
use FFMpeg\Exception\InvalidArgumentException;
use FFMpeg\Filters\AdvancedMedia\ComplexCompatibleFilter;
use FFMpeg\Format\VideoInterface;
use FFMpeg\Media\AdvancedMedia;
use FFMpeg\Media\Video;
use stdClass;

class WatermarkFilter implements VideoFilterInterface, ComplexCompatibleFilter
{
    /** @var string */
    private $watermarkPath;
    /** @var array */
    private $coordinates;
    /** @var array */
    private $setScale;
    /** @var array */
    private $setTime;
    /** @var int */
    private $priority;

    public function __construct($watermarkPath, array $coordinates = [], array $setScale = [], array $setTime, $priority = 0)
    {
        if (!file_exists($watermarkPath)) {
            throw new InvalidArgumentException(sprintf('File %s does not exist', $watermarkPath));
        }

        $this->watermarkPath = $watermarkPath;
        $this->coordinates = $coordinates;
        $this->setScale = $setScale;
        $this->setTime = $setTime;
        $this->priority = $priority;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * Get name of the filter.
     *
     * @return string
     */
    public function getName()
    {
        return 'watermark';
    }

    /**
     * Get minimal version of ffmpeg starting with which this filter is supported.
     *
     * @return string
     */
    public function getMinimalFFMpegVersion()
    {
        return '0.8';
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Video $video, VideoInterface $format)
    {
        return $this->getCommands();
    }

    /**
     * {@inheritdoc}
     */
    public function applyComplex(AdvancedMedia $media)
    {
        return $this->getCommands();
    }

    /**
     * @return array
     */
    protected function getCommands()
    {
        $position = isset($this->coordinates['position']) ? $this->coordinates['position'] : 'absolute';

        switch ($position) {
            case 'relative':
                if (isset($this->coordinates['top'])) {
                    $y = $this->coordinates['top'];
                } elseif (isset($this->coordinates['bottom'])) {
                    $y = 'main_h - ' . $this->coordinates['bottom'] . ' - overlay_h';
                } else {
                    $y = 0;
                }

                if (isset($this->coordinates['left'])) {
                    $x = $this->coordinates['left'];
                } elseif (isset($this->coordinates['right'])) {
                    $x = 'main_w - ' . $this->coordinates['right'] . ' - overlay_w';
                } else {
                    $x = 0;
                }

                break;
            default:
                $x = isset($this->coordinates['x']) ? $this->coordinates['x'] : 0;
                $y = isset($this->coordinates['y']) ? $this->coordinates['y'] : 0;
                break;
        }

        $timeStart = '';
        $timeEnd = '';
        $filterTime = '';

        if (array_key_exists('start', $this->setTime)) {
            $timeStart = $this->setTime['start'];
        }
        if (array_key_exists('end', $this->setTime)) {
            $timeEnd = $this->setTime['end'];
        }

        if ($timeStart && $timeEnd) {
            $filterTime = 'between(t,' . $timeStart . ',' . $timeEnd . ')';
        } elseif ($timeStart && !$timeEnd) {
            $filterTime = 'gte(t,' . $timeStart . ')';
        }
        $enableFilterString = ($filterTime) ? ' :enable=\'' . $filterTime . '\'' : '';

        $resize = (isset($this->setScale['w']) && isset($this->setScale['h'])) ? new Dimension($this->setScale['w'], $this->setScale['h']) : new stdClass();
        $resizeFilterString = (count((array)$resize)) ? ',scale=' . $resize->getWidth() . ':' . $resize->getHeight() : '';

        return [
            '-vf',
            'movie=' . $this->watermarkPath . $resizeFilterString . ' [watermark]; [in][watermark] overlay=' . $x . ':' . $y . $enableFilterString . ' [out]',
        ];
    }
}
