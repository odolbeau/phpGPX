<?php
/**
 * Created            17/02/2017 18:21
 * @author            Jakub Dubec <jakub.dubec@gmail.com>
 */

namespace phpGPX\Models;


use phpGPX\Helpers\DateTimeHelper;
use phpGPX\Helpers\SerializationHelper;
use phpGPX\phpGPX;

/**
 * Class Route
 * @package phpGPX\Models
 */
class Route extends Collection
{

	/**
	 * A list of route points.
	 * An original GPX 1.1 attribute.
	 * @var Point[]
	 */
	public $points;

	/**
	 * Route constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->points = [];
	}


	/**
	 * Return all points in collection.
	 * @return Point[]
	 */
	public function getPoints()
	{
		/** @var Point[] $points */
		$points = [];

		$points = array_merge($points, $this->points);

		if (phpGPX::$SORT_BY_TIMESTAMP && !empty($points))
		{
			usort($points, array(DateTimeHelper::class, 'comparePointsByTimestamp'));
		}

		return $points;
	}

	/**
	 * Serialize object to array
	 * @return array
	 */
	public function toArray()
	{
		return [
			'name' => SerializationHelper::stringOrNull($this->name),
			'cmt' => SerializationHelper::stringOrNull($this->comment),
			'desc' => SerializationHelper::stringOrNull($this->description),
			'src' => SerializationHelper::stringOrNull($this->source),
			'link' => SerializationHelper::serialize($this->links),
			'number' => SerializationHelper::integerOrNull($this->number),
			'type' => SerializationHelper::stringOrNull($this->type),
			'extensions' => SerializationHelper::serialize($this->extensions),
			'rtep' => SerializationHelper::serialize($this->points),
			'stats' => SerializationHelper::serialize($this->stats)
		];
	}

	/**
	 * Recalculate stats objects.
	 * @return void
	 */
	function recalculateStats()
	{
		if (empty($this->stats))
			$this->stats = new Stats();

		$this->stats->reset();

		if (empty($this->points))
			return;

		$pointCount = count($this->points);

		$firstPoint = &$this->points[0];
		$lastPoint = end($this->points);

		$this->stats->startedAt = $firstPoint->time;
		$this->stats->finishedAt = $lastPoint->time;
		$this->stats->minAltitude = $firstPoint->elevation;

		for ($p = 0; $p < $pointCount; $p++)
		{
			if ($p == 0)
			{
				$this->points[$p]->difference = 0;
			}
			else
			{
				$this->points[$p]->difference = GeoHelper::getDistance($this->points[$p-1], $this->points[$p]);
			}

			$this->stats->distance += $this->points[$p]->difference;
			$this->points[$p] = $this->stats->distance;
		}
		
		if($this->stats->cumulativeElevationGain === null)
		{
			$lastElevation = $firstPoint->elevation;
			$this->stats->cumulativeElevationGain = 0;
		} 
		else
		{
			$elevationDelta = $this->points[$p]->elevation - $lastElevation;
			$this->stats->cumulativeElevationGain += ($elevationDelta > 0) ? $elevationDelta : 0;
			$lastElevation = $this->points[$p]->elevation;
		}

		if ($this->stats->minAltitude == null)
		{
			$this->stats->minAltitude = $this->points[$p]->elevation;
		}

		if ($this->stats->maxAltitude < $this->points[$p]->elevation)
		{
			$this->stats->maxAltitude = $this->points[$p]->elevation;
		}

		if ($this->stats->minAltitude > $this->points[$p]->elevation)
		{
			$this->stats->minAltitude = $this->points[$p]->elevation;
		}

		if (($firstPoint instanceof \DateTime) && ($lastPoint instanceof \DateTime))
		{
			$this->stats->duration = $lastPoint->time->getTimestamp() - $firstPoint->time->getTimestamp();

			if ($this->stats->duration != 0)
			{
				$this->stats->averageSpeed = $this->stats->distance / $this->stats->duration;
			}

			if ($this->stats->distance != 0)
			{
				$this->stats->averagePace = $this->stats->duration / ($this->stats->distance / 1000);
			}
		}
	}
}
