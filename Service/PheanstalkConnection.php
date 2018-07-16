<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Service;

use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

/**
 * Class PheanstalkConnection
 * This class is a simplified wrapper around Pheanstalk for this bundle
 *
 * @package Webdevvie\PheanstalkTaskQueueBundle\Service
 */
class PheanstalkConnection
{
    /**
     * @var PheanstalkInterface
     */
    private $pheanstalk;

    /**
     * @param PheanstalkFactory $pheanstalkFactory
     * @param string $serverHost
     * @param string $defaultTube
     */
    public function __construct(PheanstalkFactory $pheanstalkFactory, $serverHost, $defaultTube)
    {
        $this->pheanstalk = $pheanstalkFactory->create($serverHost);
        $this->pheanstalk->useTube($defaultTube);
    }

    /**
     * Puts a task string into the current
     * @param $data
     * @param int $priority
     * @param int $delay
     * @param int $timeToRun
     * @return $this
     */
    public function put(
        $data,
        $priority = PheanstalkInterface::DEFAULT_PRIORITY,
        $delay = PheanstalkInterface::DEFAULT_DELAY,
        $timeToRun = PheanstalkInterface::DEFAULT_TTR
    ) {
        $this->pheanstalk->put($data, $priority, $delay, $timeToRun);
        return $this;
    }

    /**
     * Deletes a job
     * @param \Pheanstalk_Job $job
     */
    public function delete($job)
    {
        $this->pheanstalk->delete($job);
    }

    /**
     * Watches a tube
     *
     * @param string $tube
     * @return $this
     */
    public function watch($tube)
    {
        $this->pheanstalk->watch($tube);
        return $this;
    }

    /**
     * Reserves a job
     *
     * @param integer $timeout timeout in seconds
     * @return bool|object|Job
     */
    public function reserve($timeout)
    {
        return $this->pheanstalk->reserve($timeout);
    }

    /**
     * Ignores a tube
     *
     * @param $tube
     * @return $this
     * @chainable
     */
    public function ignore($tube)
    {
        $this->pheanstalk->ignore($tube);
        return $this;
    }

    /**
     * tells pheanstalk to use a certain tube
     *
     * @param $tube
     * @return $this
     */
    public function useTube($tube)
    {
        $this->pheanstalk->useTube($tube);
        return $this;
    }
}
