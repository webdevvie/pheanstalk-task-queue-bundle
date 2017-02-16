<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Service;

use Pheanstalk\PheanstalkInterface;
use Pheanstalk\Pheanstalk;

/**
 * Class PheanstalkFactory
 * This is a wrapper so it can be used in DI
 *
 * @package Webdevvie\PheanstalkTaskQueueBundle\Service
 */
class PheanstalkFactory
{
    /**
     * @param string $serverHost
     * @return Pheanstalk
     */
    public function create($serverHost)
    {
        return new Pheanstalk($serverHost);
    }
}
