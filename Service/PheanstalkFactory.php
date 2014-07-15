<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Service;

use \Pheanstalk_PheanstalkInterface;
use \Pheanstalk_Pheanstalk;

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
     * @return Pheanstalk_Pheanstalk
     */
    public function create($serverHost)
    {
        return new \Pheanstalk_Pheanstalk($serverHost);
    }
}
