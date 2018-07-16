<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Service;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

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
     * @return PheanstalkInterface
     */
    public function create($serverHost)
    {
        return new Pheanstalk($serverHost);
    }
}
